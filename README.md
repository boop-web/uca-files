# UCA Files - Web File Manager

A modern, Windows Explorer-style web-based file manager with unzipper capabilities. Built with Bootstrap 5 and a sleek dark theme inspired by VS Code.

![UCA Files](https://img.shields.io/badge/Version-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

### Windows Explorer Style UI
- **Sidebar** - Quick access to Desktop, Documents, Downloads
- **Toolbar** - Back, Forward, Up, Refresh, Upload, New Folder, Delete, Zip, Encrypt
- **Address Bar** - Breadcrumb navigation through folders
- **File List** - Details view with Name, Size, Type, Date Modified columns
- **Status Bar** - Shows selection count and total items

### File Operations
- **Upload** - Drag & drop or click to upload multiple files
- **Download** - Download single files or selected items as zip
- **Delete** - Delete multiple files and folders
- **Create Folder** - Create new folders with validation

### Unzipper Capabilities
- **Create Zip** - Select multiple files/folders and create zip archives
- **Extract Zip** - Extract any zip file to current directory
- **View Zip Contents** - Browse contents of zip files before extracting

### Code Editor
- **Edit Files** - Double-click to edit .html, .php, .css, .js, .py, .txt, and more
- **Syntax Highlighting** - Monospace font for code editing
- **Save** - Save changes with Ctrl+S or Save button

### Security - Page Encryption
- **6-Digit Passcode** - AES-256 encryption for page protection
- **Lock/Unlock** - Encrypt and decrypt the file manager with a passcode
- **Secure Storage** - Encrypted marker stored in .uca_lock file

### Navigation
- **Breadcrumb Navigation** - Click to navigate to any parent folder
- **Back/Forward Buttons** - Navigate through history
- **Go Up** - Navigate to parent directory
- **Quick Access** - Sidebar shortcuts to common locations

### File Preview
- **Image Preview** - Click on images to view in fullscreen modal
- **File Icons** - Automatic icon detection by file type
- **File Size** - Display file sizes in human-readable format

### User Interface
- **Dark Theme** - Modern dark UI with accent colors
- **Responsive** - Works on desktop, tablet, and mobile
- **Progress Indicators** - Upload progress with visual feedback
- **Multi-select** - Checkbox selection for batch operations

## Installation

1. Upload `uca-files.php` to your web server
2. Ensure PHP has write permissions for the directory
3. Access via browser

### Requirements
- PHP 7.4 or higher
- Write permissions on the directory
- ZipExtension enabled (for zip operations)

## Usage

### Navigation
- Click on folders to enter them
- Click `..` to go to parent folder
- Use breadcrumb links for quick navigation

### Upload Files
1. Click the drop zone or drag files onto it
2. Select multiple files if needed
3. Click "Upload" button
4. Watch the progress bar

### Download Files
1. Select files/folders using checkboxes
2. Click "Download" to create a zip and download
3. Or click individual download icons on files

### Create Zip
1. Select items using checkboxes
2. Click "Zip Selected" to create archive in current folder

### Extract Zip
1. Find a .zip file in the list
2. Click "Extract" button
3. Files will be extracted to current directory

### View Zip Contents
1. Click "View" button on any zip file
2. Modal shows all files and folders inside

## Security Notes

- This file manager has **no authentication** by default
- For public servers, add password protection via `.htaccess` or server configuration
- Be careful when allowing file write permissions
- Monitor uploaded files for malicious content

## File Types Supported

| Category | Extensions |
|----------|-----------|
| Images | jpg, jpeg, png, gif, webp, svg, bmp |
| Videos | mp4, webm, avi, mkv, mov |
| Audio | mp3, wav, ogg, flac, m4a |
| Documents | pdf, doc, docx, xls, xlsx, ppt, pptx |
| Archives | zip, rar, 7z, tar, gz |
| Code | html, css, js, php, json, xml, txt, md |

## Keyboard Shortcuts

- Click to select
- Shift+Click for range selection (future)

## Screenshots

The interface features:
- Dark card-based layout
- Folder/file grid display
- Action toolbar
- Upload drop zone
- Progress indicators
- Modal dialogs for operations

## License

MIT License - Feel free to use and modify!

## Author

Created for UCA - Universal Cloud Access

---

<p align="center">
  <i class="bi bi-folder2-open"></i> UCA Files - Your Web File Manager
</p>