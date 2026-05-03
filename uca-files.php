<?php
session_start();

$lock_file = '.uca_lock';
$encryption_key = 'UCA_FILES_SECRET_KEY_2024';

if (file_exists($lock_file)) {
    $locked_content = file_get_contents($lock_file);
    $decrypted = openssl_decrypt($locked_content, 'aes-256-cbc', $encryption_key);
    if ($decrypted === 'LOCKED') {
        $page_locked = true;
    }
}

if (isset($_POST['encrypt_page'])) {
    $passcode = $_POST['passcode'] ?? '';
    if (preg_match('/^\d{6}$/', $passcode)) {
        $encrypted = openssl_encrypt('LOCKED', 'aes-256-cbc', $encryption_key);
        file_put_contents($lock_file, $encrypted);
        $page_locked = true;
        $action_msg = "Page encrypted!";
    }
}

if (isset($_POST['decrypt_page'])) {
    $passcode = $_POST['passcode'] ?? '';
    if (preg_match('/^\d{6}$/', $passcode)) {
        if (file_exists($lock_file)) {
            $locked_content = file_get_contents($lock_file);
            $decrypted = openssl_decrypt($locked_content, 'aes-256-cbc', $encryption_key);
            if ($decrypted === 'LOCKED') {
                unlink($lock_file);
                $page_locked = false;
                $action_msg = "Page decrypted!";
            }
        }
    }
}

$current_dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
$current_dir = realpath($current_dir) ?: '.';
if ($current_dir === '.' || !$current_dir || !is_dir($current_dir)) {
    $current_dir = getcwd();
}

$upload_msg = '';
$action_msg = '';
$edit_file = isset($_GET['edit']) ? $_GET['edit'] : null;

if (isset($_POST['upload'])) {
    if (!empty($_FILES['files']['name'][0])) {
        $uploaded = 0;
        $errors = [];
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['files']['tmp_name'][$key];
                $dest = $current_dir . DIRECTORY_SEPARATOR . basename($name);
                if (move_uploaded_file($tmp, $dest)) {
                    $uploaded++;
                } else {
                    $errors[] = $name;
                }
            }
        }
        $upload_msg = $uploaded . ' file(s) uploaded' . (count($errors) ? ', ' . count($errors) . ' failed' : '');
    }
}

if (isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    if ($folder_name && preg_match('/^[\w\-\s]+$/', $folder_name)) {
        $new_dir = $current_dir . DIRECTORY_SEPARATOR . $folder_name;
        if (!file_exists($new_dir)) {
            mkdir($new_dir, 0755, true);
            $action_msg = "Folder '$folder_name' created";
        } else {
            $action_msg = "Folder already exists";
        }
    }
}

if (isset($_POST['create_new_file'])) {
    $file_name = trim($_POST['file_name']);
    $file_type = $_POST['file_type'] ?? 'txt';
    
    $extensions = [
        'html' => '<!DOCTYPE html>\n<html>\n<head>\n    <meta charset="UTF-8">\n    <title>New Page</title>\n</head>\n<body>\n    \n</body>\n</html>',
        'php' => '<?php\n\n// Your PHP code here\n\n?>',
        'css' => '/* Main Styles */\n\nbody {\n    font-family: Arial, sans-serif;\n    margin: 0;\n    padding: 0;\n}\n',
        'js' => '// JavaScript\n\ndocument.addEventListener("DOMContentLoaded", function() {\n    \n});\n',
        'json' => '{\n    \n}',
        'txt' => '',
        'xml' => '<?xml version="1.0" encoding="UTF-8"?>\n<root>\n    \n</root>',
        'md' => '# New Document\n\nWrite your content here...\n',
    ];
    
    $final_name = $file_name;
    if (!preg_match('/\.' . $file_type . '$/i', $file_name)) {
        $final_name = $file_name . '.' . $file_type;
    }
    
    $file_path = $current_dir . DIRECTORY_SEPARATOR . $final_name;
    if (!file_exists($file_path)) {
        $content = $extensions[$file_type] ?? '';
        file_put_contents($file_path, $content);
        $action_msg = "File '$final_name' created";
        $edit_file = $final_name;
    } else {
        $action_msg = "File already exists";
    }
}

if (isset($_POST['delete_items'])) {
    $items = $_POST['items'] ?? [];
    $deleted = 0;
    foreach ($items as $item) {
        $path = $current_dir . DIRECTORY_SEPARATOR . $item;
        if (file_exists($path)) {
            if (is_dir($path)) {
                delTree($path);
            } else {
                unlink($path);
            }
            $deleted++;
        }
    }
    $action_msg = "$deleted item(s) deleted";
}

if (isset($_POST['create_zip'])) {
    $items = $_POST['items'] ?? [];
    if (!empty($items)) {
        $zip_name = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
            foreach ($items as $item) {
                $path = $current_dir . DIRECTORY_SEPARATOR . $item;
                if (file_exists($path)) {
                    if (is_dir($path)) {
                        addToZip($zip, $path, $item);
                    } else {
                        $zip->addFile($path, $item);
                    }
                }
            }
            $zip->close();
            $action_msg = "Created $zip_name";
        }
    }
}

if (isset($_POST['extract_zip'])) {
    $zip_file = $_POST['zip_file'];
    $zip_path = $current_dir . DIRECTORY_SEPARATOR . $zip_file;
    $extract_folder = $_POST['extract_folder'] ?? '';
    
    $target_dir = $current_dir;
    if ($extract_folder) {
        $target_dir = $current_dir . DIRECTORY_SEPARATOR . $extract_folder;
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
    }
    
    if (file_exists($zip_path) && is_file($zip_path)) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) === TRUE) {
            $zip->extractTo($target_dir);
            $zip->close();
            $action_msg = "Extracted $zip_file" . ($extract_folder ? " to $extract_folder" : "");
        } else {
            $action_msg = "Failed to extract";
        }
    }
}

if (isset($_POST['create_single_zip'])) {
    $folder_name = trim($_POST['folder_zip_name']);
    $folder_path = $current_dir . DIRECTORY_SEPARATOR . $folder_name;
    if (is_dir($folder_path)) {
        $zip_name = $folder_name . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
            addToZip($zip, $folder_path, $folder_name);
            $zip->close();
            $action_msg = "Created $zip_name";
        }
    }
}

if (isset($_POST['save_file'])) {
    $file_path = $current_dir . DIRECTORY_SEPARATOR . $_POST['filename'];
    $content = $_POST['content'];
    if (file_put_contents($file_path, $content) !== false) {
        $action_msg = "File saved successfully";
        $edit_file = $_POST['filename'];
    } else {
        $action_msg = "Failed to save file";
    }
}

if (isset($_POST['rename_item'])) {
    $old_name = $_POST['old_name'];
    $new_name = trim($_POST['new_name']);
    $old_path = $current_dir . DIRECTORY_SEPARATOR . $old_name;
    $new_path = $current_dir . DIRECTORY_SEPARATOR . $new_name;
    
    if ($old_name && $new_name && file_exists($old_path) && !file_exists($new_path)) {
        rename($old_path, $new_path);
        $action_msg = "Renamed to '$new_name'";
    }
}

if (isset($_POST['protect_item_submit'])) {
    $item_name = $_POST['protect_item'];
    $password = $_POST['protect_password'];
    $password_confirm = $_POST['protect_password_confirm'];
    
    if ($password !== $password_confirm) {
        $action_msg = "Passwords do not match!";
    } else if (strlen($password) < 1) {
        $action_msg = "Password cannot be empty!";
    } else {
        $item_path = $current_dir . DIRECTORY_SEPARATOR . $item_name;
        if (file_exists($item_path)) {
            $key = 'UCA_FILE_PROTECT_' . md5($password . $item_name);
            
            if (is_dir($item_path)) {
                $content = delTreeGetContent($item_path);
                $encrypted = openssl_encrypt($content, 'aes-256-cbc', $key);
                $protected_content = json_encode(['type'=>'folder','data'=>base64_encode($encrypted),'name'=>$item_name]);
            } else {
                $content = file_get_contents($item_path);
                $encrypted = openssl_encrypt($content, 'aes-256-cbc', $key);
                $protected_content = json_encode(['type'=>'file','data'=>base64_encode($encrypted),'name'=>$item_name,'ext'=>pathinfo($item_name, PATHINFO_EXTENSION)]);
            }
            
            file_put_contents($item_path . '.uca_protected', $protected_content);
            if (is_dir($item_path)) {
                delTree($item_path);
            } else {
                unlink($item_path);
            }
            $action_msg = "Password protection applied to '$item_name'";
        }
    }
}

if (isset($_POST['unprotect_item_submit'])) {
    $item_name = $_POST['unprotect_item'];
    $password = $_POST['unprotect_password'];
    
    $protected_path = $current_dir . DIRECTORY_SEPARATOR . $item_name . '.uca_protected';
    if (file_exists($protected_path)) {
        $key = 'UCA_FILE_PROTECT_' . md5($password . $item_name);
        $protected_content = file_get_contents($protected_path);
        $decoded = json_decode($protected_content, true);
        
        if ($decoded && isset($decoded['data'])) {
            $decrypted = openssl_decrypt(base64_decode($decoded['data']), 'aes-256-cbc', $key);
            
            if ($decrypted !== false && $decrypted !== '') {
                $original_path = $current_dir . DIRECTORY_SEPARATOR . $item_name;
                
                if ($decoded['type'] === 'folder') {
                    mkdir($original_path, 0755, true);
                    restoreFolderContent($original_path, $decrypted);
                } else {
                    file_put_contents($original_path, $decrypted);
                }
                
                unlink($protected_path);
                $action_msg = "Protection removed from '$item_name'";
            } else {
                $action_msg = "Incorrect password!";
            }
        }
    }
}

function delTreeGetContent($dir) {
    $result = [];
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            $result[$file] = ['type'=>'dir','content'=>delTreeGetContent($path)];
        } else {
            $result[$file] = ['type'=>'file','content'=>file_get_contents($path)];
        }
    }
    return $result;
}

function restoreFolderContent($dir, $json) {
    $data = json_decode($json, true);
    foreach ($data as $name => $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if ($item['type'] === 'dir') {
            mkdir($path, 0755, true);
            restoreFolderContent($path, json_encode($item['content']));
        } else {
            file_put_contents($path, $item['content']);
        }
    }
}

function isProtected($filename) {
    return file_exists($filename . '.uca_protected');
}

function delTree($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? delTree($path) : unlink($path);
    }
    rmdir($dir);
}

function addToZip($zip, $folder, $base) {
    $files = scandir($folder);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = "$folder/$file";
        $name = "$base/$file";
        if (is_dir($path)) {
            $zip->addEmptyDir($name);
            addToZip($zip, $path, $name);
        } else {
            $zip->addFile($path, $name);
        }
    }
}

function formatSize($size) {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($size >= 1024 && $i < 4) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function formatDate($timestamp) {
    return date('M d, Y H:i', $timestamp);
}

function getFileIcon($ext) {
    $icons = [
        'jpg'=>'bi-image','jpeg'=>'bi-image','png'=>'bi-image','gif'=>'bi-image','svg'=>'bi-image','webp'=>'bi-image',
        'pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word',
        'xls'=>'bi-file-earmark-excel','xlsx'=>'bi-file-earmark-excel',
        'ppt'=>'bi-file-earmark-ppt','pptx'=>'bi-file-earmark-ppt',
        'zip'=>'bi-file-earmark-zip','rar'=>'bi-file-earmark-zip','7z'=>'bi-file-earmark-zip','tar'=>'bi-file-earmark-zip','gz'=>'bi-file-earmark-zip',
        'mp3'=>'bi-file-earmark-music','wav'=>'bi-file-earmark-music','ogg'=>'bi-file-earmark-music',
        'mp4'=>'bi-file-earmark-play','avi'=>'bi-file-earmark-play','mkv'=>'bi-file-earmark-play','mov'=>'bi-file-earmark-play',
        'txt'=>'bi-file-earmark-text','md'=>'bi-file-earmark-text','json'=>'bi-file-earmark-text','xml'=>'bi-file-earmark-text','html'=>'bi-file-earmark-code','css'=>'bi-file-earmark-code','js'=>'bi-file-earmark-code','php'=>'bi-file-earmark-code','py'=>'bi-file-earmark-code','c'=>'bi-file-earmark-code','cpp'=>'bi-file-earmark-code','java'=>'bi-file-earmark-code','sql'=>'bi-file-earmark-code','sh'=>'bi-file-earmark-code','bat'=>'bi-file-earmark-code','ps1'=>'bi-file-earmark-code','ini'=>'bi-file-earmark-text','log'=>'bi-file-earmark-text','htaccess'=>'bi-file-earmark-code',
    ];
    return $icons[strtolower($ext)] ?? 'bi-file-earmark';
}

function isImage($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp']);
}

function isEditable($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['txt','md','json','xml','html','htm','css','js','php','py','c','cpp','java','sql','sh','bat','ps1','ini','cfg','conf','log','htaccess','yaml','yml','sql']);
}

function isZip($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['zip','rar','7z','tar','gz']);
}

$files = [];
$folders = [];

if ($dh = opendir($current_dir)) {
    while (($item = readdir($dh)) !== FALSE) {
        if ($item === '.' || $item === '..') continue;
        $path = $current_dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }
    closedir($dh);
}

natcasesort($folders);
natcasesort($files);

$parent_dir = dirname($current_dir);
$root_dir = getcwd();
$is_root = ($current_dir === $root_dir);

$path_parts = explode(DIRECTORY_SEPARATOR, $current_dir);
$breadcrumbs = [];
$accum = '';
foreach ($path_parts as $part) {
    if ($part) {
        $accum = $accum ? $accum . DIRECTORY_SEPARATOR . $part : $part;
        $breadcrumbs[] = ['name'=>$part, 'path'=>$accum];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCA Files - File Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-dark: #1e1e1e;
            --bg-panel: #252526;
            --bg-item: #2d2d30;
            --bg-hover: #37373d;
            --border: #3e3e42;
            --accent: #0078d4;
            --accent-green: #4ec9b0;
            --text: #cccccc;
            --text-bright: #ffffff;
            --text-muted: #858585;
        }
        
        * { box-sizing: border-box; }
        
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', 'Microsoft YaHei', system-ui, sans-serif;
            font-size: 13px;
            user-select: none;
        }
        
        .explorer-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .toolbar {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .toolbar-btn {
            background: transparent;
            border: none;
            color: var(--text);
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }
        
        .toolbar-btn:hover {
            background: var(--bg-hover);
        }
        
        .toolbar-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .toolbar-divider {
            width: 1px;
            height: 20px;
            background: var(--border);
            margin: 0 4px;
        }
        
        .address-bar {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 2px;
            flex: 1;
            overflow: hidden;
        }
        
        .breadcrumb-item {
            color: var(--text);
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .breadcrumb-item:hover {
            background: var(--bg-hover);
        }
        
        .breadcrumb-item.current {
            color: var(--text-bright);
        }
        
        .breadcrumb-sep {
            color: var(--text-muted);
            margin: 0 2px;
        }
        
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .sidebar {
            width: 200px;
            background: var(--bg-panel);
            border-right: 1px solid var(--border);
            padding: 10px 0;
            flex-shrink: 0;
            overflow-y: auto;
        }
        
        .sidebar-section {
            margin-bottom: 16px;
        }
        
        .sidebar-title {
            padding: 8px 16px;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-item {
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: var(--text);
        }
        
        .sidebar-item:hover {
            background: var(--bg-hover);
        }
        
        .sidebar-item i {
            color: var(--accent);
        }
        
        .file-list-container {
            flex: 1;
            overflow: auto;
            display: flex;
            flex-direction: column;
        }
        
        .file-list-header {
            display: flex;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-panel);
            font-weight: 600;
            color: var(--text-muted);
            font-size: 12px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .file-list-header .col-name { flex: 1; }
        .file-list-header .col-size { width: 100px; text-align: right; }
        .file-list-header .col-type { width: 150px; }
        .file-list-header .col-modified { width: 180px; text-align: right; }
        
        .file-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .file-row {
            display: flex;
            padding: 4px 16px;
            cursor: pointer;
            border-bottom: 1px solid transparent;
            align-items: center;
        }
        
        .file-row:hover {
            background: var(--bg-hover);
        }
        
        .file-row.selected {
            background: var(--bg-item);
            border-color: var(--accent);
        }
        
        .file-row .col-name {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }
        
        .file-row .col-name span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-row .col-size { width: 100px; text-align: right; color: var(--text-muted); }
        .file-row .col-type { width: 150px; color: var(--text-muted); }
        .file-row .col-modified { width: 180px; text-align: right; color: var(--text-muted); }
        
        .file-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .file-icon.folder { color: #dcb67a; }
        .file-icon.image { color: #b48ead; }
        .file-icon.code { color: #4ec9b0; }
        .file-icon.archive { color: #9cdcfe; }
        .file-icon.video { color: #ce9178; }
        .file-icon.audio { color: #c586c0; }
        .file-icon.doc { color: #569cd6; }
        
        .file-checkbox {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            cursor: pointer;
        }
        
        .status-bar {
            background: var(--accent);
            color: white;
            padding: 4px 16px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .modal-content {
            background: var(--bg-panel);
            border: 1px solid var(--border);
        }
        
        .modal-header {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            color: var(--text-bright);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        .form-control, .form-select {
            background: var(--bg-item);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--bg-item);
            border-color: var(--accent);
            color: var(--text);
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.2);
        }
        
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            margin: 20px;
        }
        
        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(0, 120, 212, 0.05);
        }
        
        .btn-uca {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-uca:hover {
            background: #106ebe;
        }
        
        .btn-outline-uca {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-outline-uca:hover {
            background: var(--bg-hover);
            border-color: var(--text-muted);
        }
        
        .alert-uca {
            background: var(--bg-item);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 4px;
            margin: 10px 20px;
            padding: 10px 16px;
        }
        
        .editor-container {
            display: none;
            flex-direction: column;
            height: 100vh;
            background: #1e1e1e;
        }
        
        .editor-container.active {
            display: flex;
        }
        
        .editor-toolbar {
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .editor-filename {
            color: var(--text-bright);
            font-weight: 500;
        }
        
        .editor-textarea {
            flex: 1;
            background: #1e1e1e;
            color: #d4d4d4;
            border: none;
            padding: 16px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.5;
            resize: none;
            outline: none;
        }
        
        /* Context Menu */
        .context-menu {
            display: none;
            position: fixed;
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px 0;
            min-width: 180px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            z-index: 10000;
        }
        
        .context-menu-item {
            padding: 8px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
        }
        
        .context-menu-item:hover {
            background: var(--bg-hover);
        }
        
        .context-menu-item i {
            color: var(--accent);
            width: 16px;
        }
        
        .context-menu-divider {
            height: 1px;
            background: var(--border);
            margin: 4px 0;
        }
        
        .encryption-panel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .encryption-box {
            background: var(--bg-panel);
            border: 1px solid var(--accent);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 0 40px rgba(0, 120, 212, 0.3);
        }
        
        .encryption-box h3 {
            color: var(--text-bright);
            margin-bottom: 20px;
        }
        
        .passcode-input {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .passcode-input input {
            width: 50px;
            height: 60px;
            background: var(--bg-item);
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--text-bright);
            font-size: 24px;
            text-align: center;
            font-family: monospace;
        }
        
        .passcode-input input:focus {
            border-color: var(--accent);
            outline: none;
        }
        
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .file-list-header .col-type, .file-row .col-type,
            .file-list-header .col-modified, .file-row .col-modified {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($page_locked) && $page_locked): ?>
    <div class="encryption-panel">
        <div class="encryption-box">
            <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--accent);"></i>
            <h3>Page Locked</h3>
            <p style="color: var(--text-muted);">Enter 6-digit passcode to unlock</p>
            <form method="POST">
                <div class="passcode-input">
                    <input type="text" name="decrypt_passcode" id="decrypt1" maxlength="1" pattern="\d" inputmode="numeric" required autofocus>
                    <input type="text" name="decrypt_passcode2" id="decrypt2" maxlength="1" pattern="\d" inputmode="numeric">
                    <input type="text" name="decrypt_passcode3" id="decrypt3" maxlength="1" pattern="\d" inputmode="numeric">
                    <input type="text" name="decrypt_passcode4" id="decrypt4" maxlength="1" pattern="\d" inputmode="numeric">
                    <input type="text" name="decrypt_passcode5" id="decrypt5" maxlength="1" pattern="\d" inputmode="numeric">
                    <input type="text" name="decrypt_passcode6" id="decrypt6" maxlength="1" pattern="\d" inputmode="numeric">
                </div>
                <button type="submit" name="decrypt_page" class="btn btn-uca" style="width: 100%;">
                    <i class="bi bi-unlock"></i> Unlock
                </button>
            </form>
        </div>
    </div>
    <script>
        const inputs = ['decrypt1','decrypt2','decrypt3','decrypt4','decrypt5','decrypt6'];
        inputs.forEach((id, i) => {
            document.getElementById(id).addEventListener('input', (e) => {
                if (e.target.value && i < 5) document.getElementById(inputs[i+1]).focus();
            });
            document.getElementById(id).addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && i > 0) document.getElementById(inputs[i-1]).focus();
            });
        });
    </script>
    <?php endif; ?>

    <?php if ($edit_file && file_exists($current_dir . DIRECTORY_SEPARATOR . $edit_file)): ?>
    <div class="editor-container active">
        <div class="editor-toolbar">
            <button class="toolbar-btn" onclick="closeEditor()">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <span class="editor-filename"><i class="bi bi-file-earmark-code"></i> <?php echo htmlspecialchars($edit_file); ?></span>
            <span class="editor-status" id="editorStatus">Modified</span>
            <button class="btn-uca" style="margin-left: auto;" onclick="saveFile()">
                <i class="bi bi-save"></i> Save
            </button>
        </div>
        <textarea class="editor-textarea" id="fileEditor" spellcheck="false"><?php echo htmlspecialchars(file_get_contents($current_dir . DIRECTORY_SEPARATOR . $edit_file)); ?></textarea>
    </div>
    <form method="POST" id="saveForm" style="display: none;">
        <input type="hidden" name="save_file" value="1">
        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($edit_file); ?>">
        <input type="hidden" name="content" id="saveContent">
    </form>
    <script>
        function closeEditor() {
            if (document.getElementById('fileEditor').dataset.modified === 'true') {
                if (!confirm('Unsaved changes. Exit anyway?')) return;
            }
            window.location.href = '?dir=<?php echo urlencode($current_dir); ?>';
        }
        const editor = document.getElementById('fileEditor');
        editor.dataset.modified = 'false';
        editor.addEventListener('input', () => { editor.dataset.modified = 'true'; });
        function saveFile() {
            document.getElementById('saveContent').value = editor.value;
            document.getElementById('saveForm').submit();
        }
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveFile();
            }
        });
    </script>
    <?php else: ?>

    <div class="explorer-container">
        <div class="toolbar">
            <button class="toolbar-btn" onclick="goParent()" title="Back">
                <i class="bi bi-arrow-left"></i>
            </button>
            <button class="toolbar-btn" onclick="refreshPage()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="bi bi-cloud-upload"></i> Upload
            </button>
            <button class="toolbar-btn" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                <i class="bi bi-folder-plus"></i> New Folder
            </button>
            <button class="toolbar-btn" data-bs-toggle="modal" data-bs-target="#newFileModal">
                <i class="bi bi-file-earmark-plus"></i> New File
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" onclick="deleteSelected()">
                <i class="bi bi-trash"></i> Delete
            </button>
            <button class="toolbar-btn" onclick="zipSelected()">
                <i class="bi bi-file-earmark-zip"></i> Zip
            </button>
            <button class="toolbar-btn" onclick="extractSelected()" id="unzipBtn" style="display:none;">
                <i class="bi bi-file-earmark-arrow-down"></i> Unzip
            </button>
            <div class="toolbar-divider"></div>
            <button class="toolbar-btn" data-bs-toggle="modal" data-bs-target="#encryptModal" style="color: var(--accent);">
                <i class="bi bi-shield-lock"></i> Encrypt
            </button>
        </div>

        <div class="address-bar">
            <div class="breadcrumb">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span class="breadcrumb-sep">›</span><?php endif; ?>
                    <a class="breadcrumb-item <?php echo $i === count($breadcrumbs) - 1 ? 'current' : ''; ?>" 
                       href="?dir=<?php echo urlencode($crumb['path']); ?>">
                        <?php if ($i === 0): ?><i class="bi bi-hdd"></i><?php endif; ?>
                        <?php echo htmlspecialchars($crumb['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button class="toolbar-btn" onclick="refreshPage()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>

        <div class="main-content">
            <div class="sidebar">
                <div class="sidebar-section">
                    <div class="sidebar-title">Favorites</div>
                    <div class="sidebar-item" onclick="window.location.reload()">
                        <i class="bi bi-house"></i> This PC
                    </div>
                    <div class="sidebar-item" onclick="goUp()">
                        <i class="bi bi-arrow-up-circle"></i> Parent Folder
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-title">Actions</div>
                    <div class="sidebar-item" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="bi bi-cloud-upload"></i> Upload Files
                    </div>
                    <div class="sidebar-item" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                        <i class="bi bi-folder-plus"></i> New Folder
                    </div>
                    <div class="sidebar-item" data-bs-toggle="modal" data-bs-target="#newFileModal">
                        <i class="bi bi-file-earmark-plus"></i> New File
                    </div>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-title">Tools</div>
                    <div class="sidebar-item" data-bs-toggle="modal" data-bs-target="#encryptModal">
                        <i class="bi bi-shield-lock"></i> Encrypt Page
                    </div>
                </div>
            </div>

            <div class="file-list-container">
                <?php if ($upload_msg): ?>
                    <div class="alert-uca"><?php echo $upload_msg; ?></div>
                <?php endif; ?>
                <?php if ($action_msg): ?>
                    <div class="alert-uca"><?php echo $action_msg; ?></div>
                <?php endif; ?>

                <div class="file-list-header">
                    <div class="col-name">Name</div>
                    <div class="col-size">Size</div>
                    <div class="col-type">Type</div>
                    <div class="col-modified">Date Modified</div>
                </div>

                <form method="POST" id="fileForm" class="file-list">
                    <?php if (!$is_root): ?>
                    <div class="file-row" data-item=".." data-is-folder="true" onclick="goParent()">
                        <div class="col-name">
                            <span class="file-icon" style="color: var(--text-muted);"><i class="bi bi-arrow-up-left"></i></span>
                            <span>..</span>
                        </div>
                        <div class="col-size">--</div>
                        <div class="col-type">Folder</div>
                        <div class="col-modified"></div>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                    <?php 
                    $folder_path = $current_dir . DIRECTORY_SEPARATOR . $folder;
                    $mod_time = filemtime($folder_path);
                    ?>
                    <div class="file-row" data-item="<?php echo htmlspecialchars($folder, ENT_QUOTES); ?>" data-is-folder="true" onclick="navigateTo('<?php echo htmlspecialchars($folder); ?>')" ondblclick="navigateTo('<?php echo htmlspecialchars($folder); ?>')">
                        <div class="col-name">
                            <input type="checkbox" class="file-checkbox" name="items[]" value="<?php echo htmlspecialchars($folder); ?>" onclick="event.stopPropagation()">
                            <span class="file-icon folder"><i class="bi bi-folder2"></i></span>
                            <span><?php echo htmlspecialchars($folder); ?></span>
                        </div>
                        <div class="col-size">--</div>
                        <div class="col-type">File folder</div>
                        <div class="col-modified"><?php echo formatDate($mod_time); ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                    <?php 
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $size = filesize($current_dir . DIRECTORY_SEPARATOR . $file);
                    $mod_time = filemtime($current_dir . DIRECTORY_SEPARATOR . $file);
                    $is_img = isImage($file);
                    $is_edit = isEditable($file);
                    $is_zip = isZip($file);
                    
                    $icon_class = 'doc';
                    $icon = 'bi-file-earmark';
                    if ($is_img) { $icon_class = 'image'; $icon = 'bi-image'; }
                    elseif ($is_zip) { $icon_class = 'archive'; }
                    elseif (in_array(strtolower($ext), ['html','htm','css','js','php','py','c','cpp','java','sql','json','xml','sh','bat','ps1','txt','md','ini','cfg','log','yaml','yml','htaccess'])) { $icon_class = 'code'; $icon = getFileIcon($ext); }
                    
                    $type_name = ucfirst($ext) . ' File';
                    if (!$ext) $type_name = 'File';
                    ?>
                    <div class="file-row" data-item="<?php echo htmlspecialchars($file, ENT_QUOTES); ?>" data-is-folder="false" onclick="event.stopPropagation()">
                        <div class="col-name">
                            <input type="checkbox" class="file-checkbox" name="items[]" value="<?php echo htmlspecialchars($file); ?>" onclick="event.stopPropagation()">
                            <?php if ($is_img): ?>
                                <img src="?img=<?php echo urlencode($file); ?>&dir=<?php echo urlencode($current_dir); ?>" style="width:20px;height:20px;object-fit:cover;border-radius:2px;">
                            <?php else: ?>
                                <span class="file-icon <?php echo $icon_class; ?>"><i class="bi <?php echo $icon; ?>"></i></span>
                            <?php endif; ?>
                            <span data-item="<?php echo htmlspecialchars($file, ENT_QUOTES); ?>" class="file-name-click" onclick="doubleClick('<?php echo htmlspecialchars($file); ?>')"><?php echo htmlspecialchars($file); ?></span>
                        </div>
                        <div class="col-size"><?php echo formatSize($size); ?></div>
                        <div class="col-type"><?php echo $type_name; ?></div>
                        <div class="col-modified"><?php echo formatDate($mod_time); ?></div>
                    </div>
                    <?php endforeach; ?>
                </form>

                <?php if (empty($folders) && empty($files)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                        <i class="bi bi-folder2" style="font-size: 3rem;"></i>
                        <p>This folder is empty</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="status-bar">
            <span id="selectionStatus">0 items selected</span>
            <span><?php echo count($folders) . ' folders, ' . count($files) . ' files'; ?></span>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" onclick="contextOpen()">
            <i class="bi bi-folder2-open"></i> Open
        </div>
        <div class="context-menu-item" onclick="contextEdit()">
            <i class="bi bi-pencil"></i> Edit
        </div>
        <div class="context-menu-item" onclick="contextRename()">
            <i class="bi bi-pencil-square"></i> Rename
        </div>
        <div class="context-menu-item" onclick="contextDownload()">
            <i class="bi bi-download"></i> Download
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="contextZip()" id="ctxZipItem">
            <i class="bi bi-file-earmark-zip"></i> Compress to ZIP
        </div>
        <div class="context-menu-item" onclick="contextExtract()" id="ctxExtractItem" style="display:none;">
            <i class="bi bi-file-earmark-arrow-down"></i> Extract Here
        </div>
        <div class="context-menu-item" onclick="contextExtractTo()" id="ctxExtractToItem" style="display:none;">
            <i class="bi bi-folder-plus"></i> Extract To...
        </div>
        <div class="context-menu-item" onclick="contextViewZip()" id="ctxViewZipItem" style="display:none;">
            <i class="bi bi-eye"></i> View Contents
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="contextProtect()" id="ctxProtectItem">
            <i class="bi bi-shield-lock"></i> Password Protect
        </div>
        <div class="context-menu-item" onclick="contextUnprotect()" id="ctxUnprotectItem" style="display:none;">
            <i class="bi bi-shield-unlock"></i> Remove Protection
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="contextProperties()">
            <i class="bi bi-info-circle"></i> Properties
        </div>
        <div class="context-menu-item" onclick="contextDelete()" style="color: #f85149;">
            <i class="bi bi-trash"></i> Delete
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Upload Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body">
                        <div class="drop-zone" id="dropZone">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <p>Drag & drop files here or click to browse</p>
                            <input type="file" name="files[]" multiple id="fileInput" style="display:none">
                        </div>
                        <div id="fileList" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="upload" class="btn-uca">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus"></i> New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="text" name="folder_name" class="form-control" placeholder="Folder name" required pattern="[\w\-\s]+" title="Letters, numbers, hyphens and underscores only">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="create_folder" class="btn-uca">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New File Modal -->
    <div class="modal fade" id="newFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> Create New File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">File Name</label>
                            <input type="text" name="file_name" class="form-control" placeholder="myfile" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File Type</label>
                            <select name="file_type" class="form-select">
                                <option value="txt">Text (.txt)</option>
                                <option value="html">HTML (.html)</option>
                                <option value="php">PHP (.php)</option>
                                <option value="css">CSS (.css)</option>
                                <option value="js">JavaScript (.js)</option>
                                <option value="json">JSON (.json)</option>
                                <option value="xml">XML (.xml)</option>
                                <option value="md">Markdown (.md)</option>
                                <option value="py">Python (.py)</option>
                                <option value="sql">SQL (.sql)</option>
                                <option value="yaml">YAML (.yaml)</option>
                                <option value="ini">Config (.ini)</option>
                                <option value="log">Log (.log)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="create_new_file" class="btn-uca">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Encrypt Modal -->
    <div class="modal fade" id="encryptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Encrypt/Decrypt Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p style="color: var(--text-muted);">Enter a 6-digit passcode to encrypt or decrypt this page.</p>
                        <div class="passcode-input" style="justify-content: center; margin: 20px 0;">
                            <input type="text" name="passcode1" id="enc1" maxlength="1" pattern="\d" inputmode="numeric" required>
                            <input type="text" name="passcode2" id="enc2" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" name="passcode3" id="enc3" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" name="passcode4" id="enc4" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" name="passcode5" id="enc5" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" name="passcode6" id="enc6" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="hidden" name="passcode" id="fullPasscode">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="encrypt_page" class="btn-uca flex-fill" onclick="combinePasscode()">
                                <i class="bi bi-lock"></i> Encrypt
                            </button>
                            <button type="submit" name="decrypt_page" class="btn-uca flex-fill" onclick="combinePasscode()">
                                <i class="bi bi-unlock"></i> Decrypt
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Extract Zip Modal -->
    <div class="modal fade" id="extractZipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-zip"></i> Extract Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="zip_file" id="extractZipFile">
                        <div class="mb-3">
                            <label class="form-label">Extract to folder (optional)</label>
                            <input type="text" name="extract_folder" class="form-control" placeholder="Leave empty for current folder">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="extract_zip" class="btn-uca">Extract</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Rename</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="old_name" id="renameOldName">
                        <div class="mb-3">
                            <label class="form-label">New Name</label>
                            <input type="text" name="new_name" class="form-control" id="renameNewName" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="rename_item" class="btn-uca">Rename</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zip Content Modal -->
    <div class="modal fade" id="zipContentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-zip"></i> Archive Contents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="zip-content" id="zipContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-uca" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="background:transparent;border:none;">
                <div class="modal-body p-0 text-center">
                    <img id="viewerImage" src="" style="max-width:100%;max-height:90vh;">
                </div>
            </div>
        </div>
    </div>

    <!-- Properties Modal -->
    <div class="modal fade" id="propertiesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-info-circle"></i> Properties</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="propsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-uca" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Protect Modal -->
    <div class="modal fade" id="protectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Password Protect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="protect_item" id="protectItemName">
                        <p style="color: var(--text-muted);">Enter a password to protect this file/folder. The content will be encrypted.</p>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="protect_password" class="form-control" required placeholder="Enter password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="protect_password_confirm" class="form-control" required placeholder="Confirm password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="protect_item_submit" class="btn-uca">Protect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Protection Modal -->
    <div class="modal fade" id="unprotectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-unlock"></i> Remove Protection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="unprotect_item" id="unprotectItemName">
                        <p style="color: var(--text-muted);">Enter the password to remove protection from this file/folder.</p>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="unprotect_password" class="form-control" required placeholder="Enter password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="unprotect_item_submit" class="btn-uca">Remove Protection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let contextItem = null;
        let contextIsFolder = false;
        
        function navigateTo(folder) {
            window.location.href = '?dir=<?php echo urlencode($current_dir); ?>/' + encodeURIComponent(folder);
        }
        
        function goParent() {
            window.location.href = '?dir=<?php echo urlencode($parent_dir); ?>';
        }
        
        function goUp() {
            window.location.href = '?dir=<?php echo urlencode($parent_dir); ?>';
        }
        
        function refreshPage() {
            window.location.reload();
        }
        
        function doubleClick(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const editable = ['txt','md','json','xml','html','htm','css','js','php','py','c','cpp','java','sql','sh','bat','ps1','ini','cfg','conf','log','htaccess','yaml','yml'];
            const images = ['jpg','jpeg','png','gif','webp','svg','bmp'];
            const zips = ['zip','rar','7z','tar','gz'];
            
            if (editable.includes(ext)) {
                window.location.href = '?dir=<?php echo urlencode($current_dir); ?>&edit=' + encodeURIComponent(filename);
            } else if (images.includes(ext)) {
                document.getElementById('viewerImage').src = '?img=' + encodeURIComponent(filename) + '&dir=<?php echo urlencode($current_dir); ?>';
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            } else if (zips.includes(ext)) {
                showExtractModal(filename);
            } else {
                window.location.href = '?download=' + encodeURIComponent(filename) + '&dir=<?php echo urlencode($current_dir); ?>';
            }
        }
        
        // Context Menu - using event delegation
        document.querySelectorAll('.file-row').forEach(row => {
            row.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                const item = this.getAttribute('data-item');
                const isFolder = this.getAttribute('data-is-folder') === 'true';
                showContextMenu(e, item, isFolder);
            });
        });
        
        function showContextMenu(e, item, isFolder) {
            contextItem = item;
            contextIsFolder = isFolder;
            
            const menu = document.getElementById('contextMenu');
            menu.style.display = 'block';
            menu.style.left = e.pageX + 'px';
            menu.style.top = e.pageY + 'px';
            
            // Show/hide zip options
            const ext = item.split('.').pop().toLowerCase();
            const isZip = ['zip','rar','7z','tar','gz'].includes(ext);
            
            document.getElementById('ctxZipItem').style.display = isZip ? 'none' : 'block';
            document.getElementById('ctxExtractItem').style.display = isZip ? 'block' : 'none';
            document.getElementById('ctxExtractToItem').style.display = isZip ? 'block' : 'none';
            document.getElementById('ctxViewZipItem').style.display = isZip ? 'block' : 'none';
            
            // Show/hide protect options
            const isProtected = item.endsWith('.uca_protected');
            document.getElementById('ctxProtectItem').style.display = isFolder || isZip ? 'none' : (isProtected ? 'none' : 'block');
            document.getElementById('ctxUnprotectItem').style.display = isProtected ? 'block' : 'none';
            
            // Adjust for folder
            if (isFolder) {
                document.getElementById('ctxExtractItem').style.display = 'none';
                document.getElementById('ctxExtractToItem').style.display = 'none';
                document.getElementById('ctxViewZipItem').style.display = 'none';
                document.getElementById('ctxProtectItem').style.display = 'block';
            }
            
            document.addEventListener('click', hideContextMenu);
        }
        
        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
            document.removeEventListener('click', hideContextMenu);
        }
        
        function contextOpen() {
            if (contextIsFolder) {
                navigateTo(contextItem);
            } else {
                doubleClick(contextItem);
            }
            hideContextMenu();
        }
        
        function contextEdit() {
            if (!contextIsFolder) {
                window.location.href = '?dir=<?php echo urlencode($current_dir); ?>&edit=' + encodeURIComponent(contextItem);
            }
            hideContextMenu();
        }
        
        function contextRename() {
            document.getElementById('renameOldName').value = contextItem;
            document.getElementById('renameNewName').value = contextItem;
            new bootstrap.Modal(document.getElementById('renameModal')).show();
            hideContextMenu();
        }
        
        function contextDownload() {
            if (!contextIsFolder) {
                window.location.href = '?download=' + encodeURIComponent(contextItem) + '&dir=<?php echo urlencode($current_dir); ?>';
            }
            hideContextMenu();
        }
        
        function contextZip() {
            const form = document.getElementById('fileForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[]';
            input.value = contextItem;
            form.appendChild(input);
            
            const zipInput = document.createElement('input');
            zipInput.type = 'hidden';
            zipInput.name = 'create_zip';
            zipInput.value = '1';
            form.appendChild(zipInput);
            
            form.submit();
            hideContextMenu();
        }
        
        function contextExtract() {
            showExtractModal(contextItem);
            hideContextMenu();
        }
        
        function contextViewZip() {
            const modal = new bootstrap.Modal(document.getElementById('zipContentModal'));
            document.getElementById('zipContent').innerHTML = '<div class="text-center py-3">Loading...</div>';
            modal.show();
            
            const formData = new FormData();
            formData.append('listzip', contextItem);
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    const match = html.match(/<div class="zip-content">([\s\S]*?)<\/div>\s*<div class="modal-footer">/);
                    document.getElementById('zipContent').innerHTML = match ? match[1] : 'Failed to load';
                });
            hideContextMenu();
        }
        
        function contextDelete() {
            if (confirm('Delete "' + contextItem + '"?')) {
                const form = document.getElementById('fileForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items[]';
                input.value = contextItem;
                form.appendChild(input);
                
                const delInput = document.createElement('input');
                delInput.type = 'hidden';
                delInput.name = 'delete_items';
                delInput.value = '1';
                form.appendChild(delInput);
                
                form.submit();
            }
            hideContextMenu();
        }
        
        function contextProperties() {
            const formData = new FormData();
            formData.append('get_properties', contextItem);
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    const match = html.match(/<div id="propsContent">([\s\S]*?)<\/div>\s*<div class="modal-footer">/);
                    document.getElementById('propsContent').innerHTML = match ? match[1] : 'Cannot load properties';
                });
            
            new bootstrap.Modal(document.getElementById('propertiesModal')).show();
            hideContextMenu();
        }
        
        function contextProtect() {
            document.getElementById('protectItemName').value = contextItem;
            new bootstrap.Modal(document.getElementById('protectModal')).show();
            hideContextMenu();
        }
        
        function contextUnprotect() {
            document.getElementById('unprotectItemName').value = contextItem;
            new bootstrap.Modal(document.getElementById('unprotectModal')).show();
            hideContextMenu();
        }
        
        function contextExtractTo() {
            showExtractModal(contextItem);
            hideContextMenu();
        }
        
        function extractSelected() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Select a zip file to extract');
                return;
            }
            const file = checkboxes[0].value;
            const ext = file.split('.').pop().toLowerCase();
            if (['zip','rar','7z','tar','gz'].includes(ext)) {
                showExtractModal(file);
            } else {
                alert('Selected file is not an archive');
            }
        }
        
        function checkZipSelection() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            const unzipBtn = document.getElementById('unzipBtn');
            if (checkboxes.length === 1) {
                const file = checkboxes[0].value;
                const ext = file.split('.').pop().toLowerCase();
                if (['zip','rar','7z','tar','gz'].includes(ext)) {
                    unzipBtn.style.display = 'flex';
                } else {
                    unzipBtn.style.display = 'none';
                }
            } else {
                unzipBtn.style.display = 'none';
            }
        }
        
        function showExtractModal(filename) {
            document.getElementById('extractZipFile').value = filename;
            new bootstrap.Modal(document.getElementById('extractZipModal')).show();
        }
        
        document.querySelectorAll('.file-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            document.getElementById('selectionStatus').textContent = checkboxes.length + ' items selected';
            checkZipSelection();
        }
        
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Select items to delete');
                return;
            }
            if (confirm('Delete ' + checkboxes.length + ' item(s)?')) {
                const form = document.getElementById('fileForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_items';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        }
        
        function zipSelected() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Select items to zip');
                return;
            }
            const form = document.getElementById('fileForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'create_zip';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
        
        // Upload handlers
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => fileInput.click());
            dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                fileInput.files = e.dataTransfer.files;
                updateFileList();
            });
            fileInput.addEventListener('change', updateFileList);
        }
        
        function updateFileList() {
            if (!fileInput) return;
            const files = fileInput.files;
            const fileList = document.getElementById('fileList');
            if (files.length > 0) {
                let html = '<div class="d-flex flex-wrap gap-2">';
                for (let i = 0; i < files.length; i++) {
                    html += '<span class="badge bg-secondary">' + files[i].name + '</span>';
                }
                html += '</div>';
                fileList.innerHTML = html;
            }
        }
        
        // Encrypt passcode handlers
        const encInputs = ['enc1','enc2','enc3','enc4','enc5','enc6'];
        encInputs.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', (e) => {
                    if (e.target.value && i < 5) document.getElementById(encInputs[i+1]).focus();
                });
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && i > 0) document.getElementById(encInputs[i-1]).focus();
                });
            }
        });
        
        function combinePasscode() {
            let code = '';
            encInputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) code += el.value;
            });
            document.getElementById('fullPasscode').value = code;
        }
        
        // Prevent context menu on file list
        document.getElementById('fileForm').addEventListener('contextmenu', (e) => {
            if (e.target.closest('.file-row')) {
                // Allow custom context menu
            }
        });
    </script>
    
    <?php
    if (isset($_GET['img'])) {
        $img = $_GET['img'];
        $dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
        $path = realpath($dir . DIRECTORY_SEPARATOR . $img);
        if ($path && is_file($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','bmp'=>'image/bmp'];
            header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
            readfile($path);
            exit;
        }
    }
    
    if (isset($_POST['listzip'])) {
        $zip_file = $current_dir . DIRECTORY_SEPARATOR . $_POST['listzip'];
        if (file_exists($zip_file)) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === TRUE) {
                echo '<div class="zip-content" style="max-height:400px;overflow-y:auto;background:#1e1e1e;padding:10px;border-radius:4px;font-size:12px;">';
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $is_dir = substr($name, -1) === '/';
                    $size = $zip->getStatIndex($i)['size'];
                    echo '<div style="padding:6px 10px;border-bottom:1px solid #3e3e42;' . ($is_dir ? 'color:#dcb67a;' : '') . 'display:flex;justify-content:space-between;">';
                    echo '<span><i class="bi ' . ($is_dir ? 'bi-folder2' : 'bi-file-earmark') . '"></i> ' . htmlspecialchars($name) . '</span>';
                    echo '<span style="color:#858585;">' . ($is_dir ? '--' : formatSize($size)) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                $zip->close();
            }
            exit;
        }
    }
    
    if (isset($_POST['get_properties'])) {
        $item = $_POST['get_properties'];
        $path = $current_dir . DIRECTORY_SEPARATOR . $item;
        
        echo '<div id="propsContent">';
        if (file_exists($path)) {
            $is_dir = is_dir($path);
            $size = $is_dir ? 'Folder' : formatSize(filesize($path));
            $modified = date('F j, Y, g:i a', filemtime($path));
            $created = date('F j, Y, g:i a', filectime($path));
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $is_protected = isProtected($path);
            
            echo '<div style="padding:10px;">';
            echo '<div style="margin-bottom:15px;"><i class="bi ' . ($is_dir ? 'bi-folder2' : 'bi-file-earmark') . '" style="font-size:2rem;color:' . ($is_dir ? '#dcb67a' : '#569cd6') . ';"></i></div>';
            echo '<table style="width:100%;font-size:13px;">';
            echo '<tr><td style="color:var(--text-muted);width:120px;">Name:</td><td>' . htmlspecialchars($item) . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Type:</td><td>' . ($is_dir ? 'File Folder' : ucfirst(pathinfo($item, PATHINFO_EXTENSION)) . ' File') . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Size:</td><td>' . $size . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Modified:</td><td>' . $modified . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Created:</td><td>' . $created . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Permissions:</td><td>' . $perms . '</td></tr>';
            echo '<tr><td style="color:var(--text-muted);">Protection:</td><td>' . ($is_protected ? '<span style="color:#f85149;"><i class="bi bi-shield-lock"></i> Password Protected</span>' : '<span style="color:#4ec9b0;">None</span>') . '</td></tr>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>Item not found</p>';
        }
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn-outline-uca" data-bs-dismiss="modal">Close</button>';
        echo '</div>';
        exit;
    }
    
    if (isset($_GET['download'])) {
        $download = $_GET['download'];
        $file = $current_dir . DIRECTORY_SEPARATOR . $download;
        if (file_exists($file) && is_file($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $download . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }
    ?>
</body>
</html>