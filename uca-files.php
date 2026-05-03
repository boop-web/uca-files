<?php
session_start();

$current_dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
$current_dir = realpath($current_dir) ?: '.';
if ($current_dir === '.' || !is_dir($current_dir)) {
    $current_dir = getcwd();
}

$upload_msg = '';
$action_msg = '';

if (isset($_POST['upload'])) {
    if (!empty($_FILES['files']['name'][0])) {
        $target_dir = $current_dir;
        $uploaded = 0;
        $errors = [];
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['files']['tmp_name'][$key];
                $dest = $target_dir . DIRECTORY_SEPARATOR . $name;
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
    if (file_exists($zip_path) && is_file($zip_path)) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path) === TRUE) {
            $zip->extractTo($current_dir);
            $zip->close();
            $action_msg = "Extracted $zip_file";
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

function getFileIcon($ext) {
    $icons = [
        'jpg'=>'bi-image','jpeg'=>'bi-image','png'=>'bi-image','gif'=>'bi-image','svg'=>'bi-image','webp'=>'bi-image',
        'pdf'=>'bi-file-earmark-pdf','doc'=>'bi-file-earmark-word','docx'=>'bi-file-earmark-word',
        'xls'=>'bi-file-earmark-excel','xlsx'=>'bi-file-earmark-excel',
        'ppt'=>'bi-file-earmark-ppt','pptx'=>'bi-file-earmark-ppt',
        'zip'=>'bi-file-earmark-zip','rar'=>'bi-file-earmark-zip','7z'=>'bi-file-earmark-zip','tar'=>'bi-file-earmark-zip','gz'=>'bi-file-earmark-zip',
        'mp3'=>'bi-file-earmark-music','wav'=>'bi-file-earmark-music','ogg'=>'bi-file-earmark-music',
        'mp4'=>'bi-file-earmark-play','avi'=>'bi-file-earmark-play','mkv'=>'bi-file-earmark-play','mov'=>'bi-file-earmark-play',
        'txt'=>'bi-file-earmark-text','md'=>'bi-file-earmark-text','json'=>'bi-file-earmark-text','xml'=>'bi-file-earmark-text','html'=>'bi-file-earmark-code','css'=>'bi-file-earmark-code','js'=>'bi-file-earmark-code','php'=>'bi-file-earmark-code',
    ];
    return $icons[strtolower($ext)] ?? 'bi-file-earmark';
}

function getMimeType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg',
        'mp4'=>'video/mp4','webm'=>'video/webm',
        'pdf'=>'application/pdf',
    ];
    return $types[$ext] ?? 'application/octet-stream';
}

function isImage($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp']);
}

function isVideo($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4','webm','avi','mkv','mov']);
}

function isAudio($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['mp3','wav','ogg','flac','m4a']);
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

$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';

if ($sort === 'size') {
    usort($files, function($a, $b) use ($current_dir) {
        return filesize($current_dir . DIRECTORY_SEPARATOR . $a) - filesize($current_dir . DIRECTORY_SEPARATOR . $b);
    });
} elseif ($sort === 'date') {
    usort($files, function($a, $b) use ($current_dir) {
        return filemtime($current_dir . DIRECTORY_SEPARATOR . $b) - filemtime($current_dir . DIRECTORY_SEPARATOR . $a);
    });
} elseif ($sort === 'type') {
    usort($files, function($a, $b) {
        return strcmp(pathinfo($a, PATHINFO_EXTENSION), pathinfo($b, PATHINFO_EXTENSION));
    });
} else {
    natcasesort($files);
}

if ($order === 'desc') {
    $folders = array_reverse($folders);
    $files = array_reverse($files);
}

$parent_dir = dirname($current_dir);
if ($parent_dir !== realpath('.')) {
    $folders = array_merge(['..'], $folders);
}

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
            --bg-dark: #0d1117;
            --bg-card: #161b22;
            --bg-hover: #21262d;
            --border: #30363d;
            --accent: #58a6ff;
            --accent-green: #3fb950;
            --accent-orange: #d29922;
            --accent-red: #f85149;
            --text: #c9d1d9;
            --text-muted: #8b949e;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
        }
        
        .navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 12px 0;
        }
        
        .navbar-brand {
            color: var(--accent) !important;
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .navbar-brand i { margin-right: 8px; }
        
        .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            color: var(--text-muted);
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
        }
        
        .btn-uca {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-uca:hover {
            background: #4090e0;
            transform: translateY(-1px);
        }
        
        .btn-uca:disabled {
            opacity: 0.5;
            transform: none;
        }
        
        .btn-outline-uca {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-outline-uca:hover {
            background: var(--accent);
            color: #fff;
        }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            padding: 16px;
        }
        
        .file-item {
            background: var(--bg-hover);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .file-item:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .file-item.selected {
            border-color: var(--accent-green);
            background: rgba(63, 185, 80, 0.1);
        }
        
        .file-item input[type="checkbox"] {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .file-icon {
            font-size: 2.5rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .file-icon.img-icon {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin: 0 auto 8px;
        }
        
        .file-name {
            font-size: 0.85rem;
            word-break: break-all;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .file-size {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .action-bar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .action-bar .btn {
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        
        .selection-info {
            color: var(--accent-green);
            font-size: 0.9rem;
            margin-left: auto;
        }
        
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
        }
        
        .form-control, .form-select {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--bg-dark);
            border-color: var(--accent);
            color: var(--text);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
        }
        
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(88, 166, 255, 0.05);
        }
        
        .drop-zone i {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .progress-container {
            display: none;
            margin-top: 15px;
        }
        
        .progress-container.active {
            display: block;
        }
        
        .alert-uca {
            background: var(--bg-hover);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 6px;
        }
        
        .sort-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .sort-link:hover, .sort-link.active {
            color: var(--accent);
        }
        
        .zip-content {
            max-height: 300px;
            overflow-y: auto;
            background: var(--bg-dark);
            border-radius: 4px;
            padding: 8px;
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        .zip-item {
            padding: 4px 8px;
            border-bottom: 1px solid var(--border);
        }
        
        .zip-item:last-child { border-bottom: none; }
        
        .zip-folder { color: var(--accent); }
        .zip-file { color: var(--text); }
        
        @media (max-width: 768px) {
            .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .selection-info {
                margin-left: 0;
                text-align: center;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid px-3">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-folder2-open"></i> UCA Files
            </a>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?><li class="breadcrumb-item"><?php else: ?><li class="breadcrumb-item active"><?php endif; ?>
                            <a href="?dir=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
    </nav>

    <div class="container-fluid px-3 py-3">
        <?php if ($upload_msg): ?>
            <div class="alert alert-info alert-uca"><?php echo $upload_msg; ?></div>
        <?php endif; ?>
        
        <?php if ($action_msg): ?>
            <div class="alert alert-success alert-uca"><?php echo $action_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-cloud-upload"></i> Upload Files</span>
                <span class="sort-link">
                    Sort: 
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=name&order=<?php echo $sort==='name'&&$order==='asc'?'desc':'asc'; ?>" class="<?php echo $sort==='name'?'active':''; ?>">Name</a> |
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=size&order=<?php echo $sort==='size'&&$order==='asc'?'desc':'asc'; ?>" class="<?php echo $sort==='size'?'active':''; ?>">Size</a> |
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=date&order=<?php echo $sort==='date'&&$order==='asc'?'desc':'asc'; ?>" class="<?php echo $sort==='date'?'active':''; ?>">Date</a> |
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=type&order=<?php echo $sort==='type'&&$order==='asc'?'desc':'asc'; ?>" class="<?php echo $sort==='type'?'active':''; ?>">Type</a>
                </span>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="drop-zone" id="dropZone">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <p>Drag & drop files here or click to browse</p>
                        <input type="file" name="files[]" multiple id="fileInput" style="display:none">
                    </div>
                    <div id="fileList" class="mt-2"></div>
                    <div class="progress-container" id="uploadProgress">
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar" id="progressBar" style="width:0%"></div>
                        </div>
                        <small class="text-muted mt-1" id="progressText">Uploading...</small>
                    </div>
                    <button type="submit" name="upload" class="btn btn-uca mt-3" id="uploadBtn" style="display:none">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </form>
            </div>
        </div>

        <div class="action-bar">
            <button class="btn btn-outline-uca" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                <i class="bi bi-folder-plus"></i> New Folder
            </button>
            <button class="btn btn-outline-uca" onclick="downloadSelected()">
                <i class="bi bi-download"></i> Download
            </button>
            <button class="btn btn-outline-uca" onclick="zipSelected()">
                <i class="bi bi-file-earmark-zip"></i> Zip Selected
            </button>
            <button class="btn btn-outline-uca" onclick="deleteSelected()" style="color: var(--accent-red);">
                <i class="bi bi-trash"></i> Delete
            </button>
            <span class="selection-info" id="selectionInfo"></span>
        </div>

        <form method="POST" id="fileForm">
            <div class="file-grid">
                <?php foreach ($folders as $folder): ?>
                    <?php 
                    $folder_path = $current_dir . DIRECTORY_SEPARATOR . $folder;
                    $is_parent = $folder === '..';
                    $href = $is_parent ? '?dir=' . urlencode($parent_dir) : '?dir=' . urlencode($folder_path);
                    ?>
                    <div class="file-item" onclick="if(event.target.type!=='checkbox')window.location='<?php echo $href; ?>'">
                        <input type="checkbox" name="items[]" value="<?php echo htmlspecialchars($folder); ?>" onchange="updateSelection()">
                        <span class="file-icon" style="color: #d29922;"><?php echo $folder === '..' ? '<i class="bi bi-arrow-return-left"></i>' : '<i class="bi bi-folder"></i>'; ?></span>
                        <div class="file-name"><?php echo htmlspecialchars($folder); ?></div>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($files as $file): ?>
                    <?php 
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $size = filesize($current_dir . DIRECTORY_SEPARATOR . $file);
                    $is_img = isImage($file);
                    $is_vid = isVideo($file);
                    $is_aud = isAudio($file);
                    $is_zip = strtolower($ext) === 'zip';
                    ?>
                    <div class="file-item" onclick="event.stopPropagation()">
                        <input type="checkbox" name="items[]" value="<?php echo htmlspecialchars($file); ?>" onchange="updateSelection()">
                        <?php if ($is_img): ?>
                            <img src="?img=<?php echo urlencode($file); ?>&dir=<?php echo urlencode($current_dir); ?>" class="file-icon img-icon" onclick="viewFile('<?php echo htmlspecialchars($file); ?>')">
                        <?php else: ?>
                            <span class="file-icon" style="color: var(--accent);"><i class="bi <?php echo getFileIcon($ext); ?>"></i></span>
                        <?php endif; ?>
                        <div class="file-name"><?php echo htmlspecialchars($file); ?></div>
                        <div class="file-size"><?php echo formatSize($size); ?></div>
                        <?php if ($is_zip): ?>
                            <button type="button" class="btn btn-sm btn-outline-uca mt-1" onclick="viewZip('<?php echo htmlspecialchars($file); ?>')">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="submit" name="extract_zip" value="<?php echo htmlspecialchars($file); ?>" class="btn btn-sm btn-outline-uca mt-1">
                                <i class="bi bi-file-earmark-zip"></i> Extract
                            </button>
                        <?php endif; ?>
                        <a href="?download=<?php echo urlencode($file); ?>&dir=<?php echo urlencode($current_dir); ?>" class="btn btn-sm btn-outline-uca mt-1">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if (empty($folders) && empty($files)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder2" style="font-size: 4rem;"></i>
                <p class="mt-3">This folder is empty</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus"></i> Create New Folder</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="text" name="folder_name" class="form-control" placeholder="Folder name" required pattern="[\w\-\s]+" title="Letters, numbers, hyphens and underscores only">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="create_folder" class="btn btn-uca">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Zip Modal -->
    <div class="modal fade" id="createZipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-zip"></i> Create Zip Archive</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="text" name="folder_zip_name" class="form-control" placeholder="Folder name to zip" required>
                        <small class="text-muted">Enter the exact folder name to create a zip archive</small>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="create_single_zip" class="btn btn-uca">Create Zip</button>
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
                    <h5 class="modal-title"><i class="bi bi-file-earmark-zip"></i> Zip Contents</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="zip-content" id="zipContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-uca" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="background:transparent;border:none;">
                <div class="modal-body text-center p-0">
                    <img id="viewerImage" src="" style="max-width:100%;max-height:90vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadBtn = document.getElementById('uploadBtn');
        const fileList = document.getElementById('fileList');
        const uploadForm = document.getElementById('uploadForm');
        
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
        
        function updateFileList() {
            const files = fileInput.files;
            if (files.length > 0) {
                uploadBtn.style.display = 'inline-block';
                let html = '<div class="d-flex flex-wrap gap-2">';
                for (let i = 0; i < files.length; i++) {
                    html += `<span class="badge bg-secondary">${files[i].name}</span>`;
                }
                html += '</div>';
                fileList.innerHTML = html;
            } else {
                uploadBtn.style.display = 'none';
                fileList.innerHTML = '';
            }
        }
        
        uploadForm.addEventListener('submit', function() {
            const progress = document.getElementById('uploadProgress');
            const bar = document.getElementById('progressBar');
            const text = document.getElementById('progressText');
            
            progress.classList.add('active');
            
            let pct = 0;
            const interval = setInterval(() => {
                pct += Math.random() * 20;
                if (pct > 90) pct = 90;
                bar.style.width = pct + '%';
                text.textContent = `Uploading... ${Math.round(pct)}%`;
            }, 200);
            
            window.onload = function() {
                clearInterval(interval);
                bar.style.width = '100%';
                text.textContent = 'Complete!';
            };
        });
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            const info = document.getElementById('selectionInfo');
            if (checkboxes.length > 0) {
                info.textContent = checkboxes.length + ' item(s) selected';
            } else {
                info.textContent = '';
            }
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
        
        function downloadSelected() {
            const checkboxes = document.querySelectorAll('input[name="items[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Select items to download');
                return;
            }
            
            const form = document.getElementById('fileForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'create_zip';
            input.value = '1';
            form.appendChild(input);
            form.action = '?dir=<?php echo urlencode($current_dir); ?>&download=1';
            form.submit();
            setTimeout(() => form.action = '', 100);
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
        
        function viewZip(filename) {
            const modal = new bootstrap.Modal(document.getElementById('zipContentModal'));
            const content = document.getElementById('zipContent');
            content.innerHTML = '<div class="text-center py-3"><span class="loading-spinner"></span> Loading...</div>';
            modal.show();
            
            const formData = new FormData();
            formData.append('listzip', filename);
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    const match = html.match(/<div class="zip-content">([\s\S]*?)<\/div>\s*<div class="modal-footer">/);
                    content.innerHTML = match ? match[1] : 'Failed to load contents';
                });
        }
        
        function viewFile(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            if (['jpg','jpeg','png','gif','webp','svg'].includes(ext)) {
                document.getElementById('viewerImage').src = '?img=' + encodeURIComponent(filename) + '&dir=<?php echo urlencode($current_dir); ?>';
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }
        }
    </script>
    
    <?php
    if (isset($_GET['img'])) {
        $img = $_GET['img'];
        $dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
        $path = $dir . DIRECTORY_SEPARATOR . $img;
        if (file_exists($path) && is_file($path)) {
            header('Content-Type: ' . getMimeType($path));
            readfile($path);
            exit;
        }
    }
    
    if (isset($_POST['listzip'])) {
        $zip_file = $current_dir . DIRECTORY_SEPARATOR . $_POST['listzip'];
        if (file_exists($zip_file)) {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) === TRUE) {
                echo '<div class="zip-content">';
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    $is_dir = substr($name, -1) === '/';
                    echo '<div class="zip-item ' . ($is_dir ? 'zip-folder' : 'zip-file') . '">';
                    echo '<i class="bi ' . ($is_dir ? 'bi-folder' : 'bi-file-earmark') . '"></i> ';
                    echo htmlspecialchars($name);
                    echo '</div>';
                }
                echo '</div>';
                $zip->close();
            }
            exit;
        }
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