# JCE Repeatable Subform Integration Fix for Joomla 6

[![Joomla! 6.0](https://img.shields.io/badge/Joomla%20%21-6.0-blue.svg?style=flat-square)](https://joomla.org)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-green.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

An elegant, robust, and **100% upgrade-safe** system plugin for Joomla 6 that fixes JCE's image selection, file browser modal integration, and field ID corruption inside repeatable subforms.

---

## 💡 The Problem (Why this plugin is needed)

When utilizing JCE's custom media fields (`type="mediajce"`) inside Joomla 6's repeatable subforms, several core issues occur:
1. **JavaScript Integration Breakdown**: JCE's legacy script relies on Joomla 3 callback routines which fail to interact with Joomla 6's custom Web Components (like `<joomla-field-media>`). This breaks the "Select" and "Insert" button behaviors. Clicking "Select" may open the media browser modal, but selecting an image and clicking "Insert" inside the JCE modal does absolutely nothing—failing to populate the target input field or update the visual preview.
2. **DOM ID Corruption Bug**: JCE's dynamic row-addition script (`media.min.js`) contains a bug. When a new row is added, it queries all media input fields in the row and overwrites their IDs with the ID of the first instance. Consequently, adding multiple media fields (e.g., Desktop, Tablet, and Mobile image inputs in the same row) breaks the field references, making it impossible to insert files into the correct siblings. Clicking "Insert" on any field inside the subform would always overwrite only the very first field.

---

## 🛠️ The Solution

This system plugin resolves these issues globally and transparently without touching a single line of JCE or Joomla core code:

1. **DOM Capture Interception**: It registers a native capture-phase listener (`useCapture = true`) on the `subform-row-add` event. Because the capture phase executes before jQuery's bubbling event phase (which JCE uses), we successfully record the correct, distinct input IDs created by Joomla before JCE's script corrupts them.
2. **Dynamic DOM & URL Restoration**: After JCE runs, the plugin restores the correct distinct IDs on the inputs and updates the parameters for all modal frames, `data-url` attributes, JCE iframe query strings (`fieldid=...`), and legacy action links.
3. **Web Component & Media Insertion Bridge**: When a JCE dialog iframe finishes loading, it wraps JCE's callback to find the parent `<joomla-field-media>` element. When the user selects a file and clicks the **Insert** button inside the JCE File Browser modal, our bridge intercepts the callback, executes the native `.setValue(url)` method of the target `<joomla-field-media>` component, and correctly populates the target input while instantly updating the visual media preview.
4. **Modal Window Closure**: It intercepts JCE's close routine to correctly shut down the modern Joomla 6 dialog manager.

---

## 🚀 Installation & Setup

1. **Download the Repository**:
   Clone or download this repository.
2. **Package as Zip**:
   Compress the contents into a standard `.zip` file (containing `jcesubformfix.php`, `jcesubformfix.xml`, `services/`, `src/`, `language/`, etc.).
3. **Install in Joomla 6**:
   Go to your Joomla Backend → **System** → **Install** → **Extensions**, and upload the zip file.
4. **Enable the Plugin**:
   Go to **System** → **Manage** → **Plugins**, search for **System - JCE repeatable Subform integration Fix** (`jcesubformfix`), and enable it.

---

## 🌍 Languages Supported

- 🇺🇸 **English (`en-GB`)**
- 🇧🇷 **Portuguese (`pt-BR`)**

---

## 📄 License

This project is licensed under the GNU General Public License v2 or later. See the `LICENSE.txt` file for details.
