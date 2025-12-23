<?php
function fetchRun2PixToJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0');
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$html) {
        return json_encode(['error' => 'Failed to fetch the page. HTTP Code: ' . $httpCode]);
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query("//table//tr");
    $data = [];
    $headers = [];
    foreach ($rows as $index => $row) {
        $cells = $row->getElementsByTagName('td');
        // If no <td>, check for <th> (Header row)
        if ($cells->length === 0) {
            $cells = $row->getElementsByTagName('th');
            foreach ($cells as $cell) {
                $headers[] = trim($cell->nodeValue);
            }
            continue;
        }
        // Process data rows
        $rowData = [];
        foreach ($cells as $cellIndex => $cell) {
            // Use header name as key if available, otherwise use index
            $key = isset($headers[$cellIndex]) ? $headers[$cellIndex] : "column_$cellIndex";
            if (in_array($key, ['Bibnr','Name','Category','OfficialTime','RankAll','RankCat', 'NetTime'])) {
                $rowData[$key] = trim($cell->nodeValue);
            }
        }
        if (intval($rowData['Bibnr'])>0) $data[] = $rowData;
    }
    return json_encode([
        'source' => $url,
        'total_records' => count($data),
        'results' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

include('config.php');
parse_str(parse_url($baseUrl, PHP_URL_QUERY),$output);
$file_prefix=$output['EventCode'].'-'.$output['Race'];
for ($p=$min_pagenum;$p<=$max_pagenum;$p++) {
    $targetUrl = $baseUrl."&pagenum=".$p;
    $json_file=$file_prefix.'-'.str_pad($p,2,'0',STR_PAD_LEFT).'.json'; // 頁數部份 2 碼前補 0, 01 02 03
    echo "$targetUrl to $json_file\n";
    $json=fetchRun2PixToJson($targetUrl);
    file_put_contents($json_file, $json);
    if ($p<$max_pagenum) sleep(1); // 停頓, 不要抓太快
}
?>