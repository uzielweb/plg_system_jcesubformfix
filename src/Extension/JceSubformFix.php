<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Jcesubformfix
 *
 * @copyright   Copyright (C) 2026 Uziel - Ponto Mega. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\JceSubformFix\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * JCE Subform Integration Fix Plugin.
 *
 * @since  1.0.0
 */
final class JceSubformFix extends CMSPlugin
{
    /**
     * Prepare the form to make sure Joomla's subforms support JCE media fields.
     *
     * @param   Form   $form  The form to be altered.
     * @param   mixed  $data  The associated data for the form.
     *
     * @return  boolean
     */
    public function onContentPrepareForm(Form $form, $data = [])
    {
        $app = Factory::getApplication();

        // Only on admin panel
        if (!$app->isClient('administrator')) {
            return true;
        }

        // Add the JCE media field path automatically to ensure mediajce loads cleanly
        $form->addFieldPath(JPATH_PLUGINS . '/fields/mediajce/fields');

        // Check if this form contains a JCE media field (mediajce)
        $xml = $form->getXml();
        if ($xml) {
            $fields = $xml->xpath('//field[@type="mediajce"]');
            if (!empty($fields)) {
                // Prepare assets and inject JS subform patch
                $this->injectJceAssetsAndPatch();
            }
        }

        return true;
    }

    /**
     * Inject JCE Media manager scripts, styles and the custom subform JS patch.
     *
     * @return  void
     */
    private function injectJceAssetsAndPatch()
    {
        $document = Factory::getDocument();
        
        // Ensure jQuery is loaded
        \Joomla\CMS\HTML\HTMLHelper::_('jquery.framework');

        $option = Factory::getApplication()->input->getCmd('option');
        $component = \Joomla\CMS\Component\ComponentHelper::getComponent($option);
        $contextId = $component ? (int) $component->id : 0;

        // Add JCE system script options
        $document->addScriptOptions('plg_system_jce', array('context' => $contextId), true);

        // Add JCE script and style assets
        $document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/media/com_jce/site/js/media.min.js', array('version' => 'auto'));
        $document->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true) . '/media/com_jce/site/css/media.min.css', array('version' => 'auto'));

        // Inject the custom bridging fix to link JCE's iframe modal with Joomla 4/5/6 custom elements and joomla-dialog
        $jsFix = "
(function() {
    if (window.jceSubformPatchApplied) return;
    window.jceSubformPatchApplied = true;

    // Listen to subform-row-add in capture phase (before JCE's bubble-phase listener corrupts input IDs)
    document.addEventListener('subform-row-add', function(e) {
        var row = e.detail && e.detail.row;
        if (!row) return;

        // Find all media inputs in this row and record their original correct, distinct IDs
        var inputs = row.querySelectorAll('.field-media-input');
        var originalInputs = [];
        inputs.forEach(function(inp) {
            originalInputs.push({
                el: inp,
                id: inp.getAttribute('id')
            });
        });

        // Set a timeout to restore the IDs and correct JCE parameters after JCE has finished executing its event handler
        setTimeout(function() {
            originalInputs.forEach(function(item) {
                if (item.el && item.id) {
                    // Restore the correct ID
                    item.el.setAttribute('id', item.id);

                    // Find parent wrapper and update its JCE parameters/URLs
                    var wrapper = item.el.closest('joomla-field-media, .wf-media-wrapper');
                    if (wrapper) {
                        var url = wrapper.getAttribute('url') || wrapper.dataset.url;
                        if (url) {
                            var newUrl = url.replace(/([?&])fieldid=[^&]*/, '$1fieldid=' + encodeURIComponent(item.id));
                            wrapper.setAttribute('url', newUrl);
                            wrapper.dataset.url = newUrl;
                            if (window.jQuery) {
                                window.jQuery(wrapper).data('url', newUrl);
                            }

                            // Update iframe src inside modal if it exists
                            var iframe = wrapper.querySelector('.joomla-modal iframe');
                            if (iframe) {
                                var iframeSrc = iframe.getAttribute('src');
                                if (iframeSrc) {
                                    var newIframeSrc = iframeSrc.replace(/([?&])fieldid=[^&]*/, '$1fieldid=' + encodeURIComponent(item.id));
                                    iframe.setAttribute('src', newIframeSrc);
                                }
                            }

                            // Update data-iframe and data-url on the modal trigger element
                            var modal = wrapper.querySelector('.joomla-modal');
                            if (modal) {
                                var dataIframe = modal.getAttribute('data-iframe');
                                if (dataIframe) {
                                    var newDataIframe = dataIframe.replace(/([?&])fieldid=[^&]*/, '$1fieldid=' + encodeURIComponent(item.id));
                                    modal.setAttribute('data-iframe', newDataIframe);
                                }
                                modal.setAttribute('data-url', newUrl);
                            }

                            // Update legacy modal buttons if they exist
                            var linkBtn = wrapper.querySelector('a.modal');
                            if (linkBtn) {
                                var href = linkBtn.getAttribute('href');
                                if (href) {
                                    var newHref = href.replace(/([?&])fieldid=[^&]*/, '$1fieldid=' + encodeURIComponent(item.id));
                                    linkBtn.setAttribute('href', newHref);
                                }
                            }
                        }
                    }
                }
            });
        }, 15);
    }, true); // useCapture = true is crucial here

    document.addEventListener('joomla-dialog:open', function(e) {
        var dialog = e.target;
        if (!dialog) return;
        var iframe = dialog.querySelector('iframe');
        if (iframe) {
            iframe.addEventListener('load', function() {
                try {
                    var iframeWin = iframe.contentWindow;
                    if (iframeWin && iframeWin.Browser) {
                        if (iframeWin.Browser.params && !iframeWin.Browser.params._patched) {
                            iframeWin.Browser.params._patched = true;
                            var originalCallback = iframeWin.Browser.params.callback;
                            iframeWin.Browser.params.callback = function(selected, data) {
                                var win = window;
                                var options = iframeWin.BrowserDialog.settings;
                                if (options && options.element) {
                                    var value = (data && data[0]) ? data[0].url : '';
                                    var inputEl = win.document.getElementById(options.element);
                                    if (inputEl) {
                                        var wrapper = inputEl.closest('joomla-field-media');
                                        if (wrapper && typeof wrapper.setValue === 'function') {
                                            wrapper.setValue(value);
                                            return;
                                        }
                                    }
                                }
                                if (originalCallback) {
                                    originalCallback(selected, data);
                                }
                            };
                        }
                        if (iframeWin.Browser.editor && !iframeWin.Browser.editor._patched) {
                            iframeWin.Browser.editor._patched = true;
                            var originalClose = iframeWin.Browser.editor.close;
                            iframeWin.Browser.editor.close = function() {
                                if (window.Joomla && window.Joomla.Modal && window.Joomla.Modal.getCurrent()) {
                                    window.Joomla.Modal.getCurrent().close();
                                    return;
                                }
                                if (originalClose) {
                                    originalClose();
                                }
                            };
                        }
                    }
                } catch(err) {
                    console.error('JCE subform fix error:', err);
                }
            });
        }
    });
})();
";
        $document->addScriptDeclaration($jsFix);
    }
}
