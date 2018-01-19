<?php
function downloadfilex($filex)
{

if (file_exists($filex)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.basename($filex).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filex));
    ob_clean();   // discard any data in the output buffer (if possible)
	flush();      // flush headers (if possible)
    return readfile($filex);

}

exit();
}
?>