<?php
$dir = dirname(__DIR__);
print_r(__DIR__ . '/..');
print_r($_SERVER['DOCUMENT_ROOT']);

// if ( is_dir( $_SERVER['DOCUMENT_ROOT']) ) {
// 	$it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
// 	$files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
// 	foreach ( $files as $file ) {
// 		if ( $file->isDir() ) {
// 			rmdir( $file->getRealPath() );
// 		} else {
// 			unlink( $file->getRealPath() );
// 		}
// 	}
// 	rmdir( $dir );
// } else {
// 	echo 'Error: Path is not a valid directory.';
// }
// echo 'Nuked That Directory.';
$current_file_name = basename( __FILE__ );
$current_dir       = $_SERVER['DOCUMENT_ROOT'];
$target_folder = basename($current_dir);
$di = new RecursiveDirectoryIterator($current_dir, FilesystemIterator::SKIP_DOTS);
$ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
foreach ($ri as $file) {
    if (strpos($file->getPath(), $target_folder) && $current_file_name != $file->getFilename()) {
        // Check exceptions in the target folder (any entry in the filepath)
        $is_exception = false;
        foreach ($exceptions as $exception) {
            if (false !== strpos($file->getPathname(), $exception)) {
                $is_exception         = true;
                $exceptions_results[] = str_replace($exception, '<strong style="color:red;">' . $exception . '</strong>', $file->getPathname());
                break;
            }
        }
        // Delete dir or file
        if (!$is_exception) {
            // Empty dir ('.' and '..')
            if ($file->isDir() && count(scandir($file->getPathname())) == 2) {
                // delete the dir
                rmdir($file);
                echo 'Nuked That Directory.';
            } else if (!$file->isDir()) {
                // delete the file
                unlink($file);
                echo 'Nuked That Directory.';
            }
        }
    }
}
