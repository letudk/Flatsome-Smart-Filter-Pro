<?php
/**
 * Simple PO to MO compiler/converter
 */
function compile_po_to_mo($po_file, $mo_file) {
    $po_content = file_get_contents($po_file);
    if (!$po_content) return false;

    $entries = array();
    preg_match_all('/msgid\s+"(.*)"\s+msgstr\s+"(.*)"/U', $po_content, $matches);
    
    for ($i = 0; $i < count($matches[1]); $i++) {
        $msgid = $matches[1][$i];
        $msgstr = $matches[2][$i];
        if ($msgid && $msgstr) {
            $entries[$msgid] = $msgstr;
        }
    }

    ksort($entries);
    $count = count($entries);
    $ids = implode("\0", array_keys($entries)) . "\0";
    $strs = implode("\0", array_values($entries)) . "\0";

    $ids_len = strlen($ids);
    $strs_len = strlen($strs);

    $header = pack('L*', 0x950412de, 0, $count, 28, 28 + ($count * 8), 0, 0);
    $mo_content = $header;

    $id_offset = 28 + ($count * 16);
    $str_offset = $id_offset + $ids_len;

    $id_table = '';
    $current_id_offset = 0;
    foreach (array_keys($entries) as $id) {
        $len = strlen($id);
        $id_table .= pack('L*', $len, $id_offset + $current_id_offset);
        $current_id_offset += $len + 1;
    }

    $str_table = '';
    $current_str_offset = 0;
    foreach (array_values($entries) as $str) {
        $len = strlen($str);
        $str_table .= pack('L*', $len, $str_offset + $current_str_offset);
        $current_str_offset += $len + 1;
    }

    $mo_content .= $id_table . $str_table . $ids . $strs;
    return file_put_contents($mo_content_file = $mo_file, $mo_content);
}

$po = 'languages/flatsome-filter-en_US.po';
$mo = 'languages/flatsome-filter-en_US.mo';

if (compile_po_to_mo($po, $mo)) {
    echo "Successfully compiled $po to $mo\n";
} else {
    echo "Failed to compile\n";
}
unlink(__FILE__);
