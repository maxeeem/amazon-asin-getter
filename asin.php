<html>
<title>Amazon ASIN Getter</title>
<head></head>
<body>

<?php

{# iNCLUDES

require ".config.inc.php";

}

{# FUNCTiONS

function getFile($source) {

	global $partial;
	
	$headers = fgetcsv($source);

	while ($row = fgetcsv($source)) {
	
		foreach ($row as $i => $datum) $data[$headers[$i]] = $datum;
		
		$rows[] = $data;
		
	}

	fclose($source);
	
	if (!is_null($partial)) {
	
		foreach ($rows as $r => $row)	if (array_key_exists($row['Part Number'], $partial)) unset($rows[$r]);
	
	}
	
	return $rows;

}

function getProcessed() {

	global $out_dir, $a_headers;

	$file = fopen($out_dir . $_FILES['userfile']['name'], "r");
	
	$skip = fgetcsv($file);
	
	while ($row = fgetcsv($file)) {
	
		foreach ($row as $i => $datum) $data[$a_headers[$i]] = $datum;
		
		$rows[$data['SKU']] = $data;
		
	}
	
	fclose($file);
	
	return $rows;

}

function makeString($query) {

	$base_url = "https://mws.amazonservices.com/Products/2011-10-01";

	$method = "GET";

	$host = "mws.amazonservices.com";

	$uri = "/Products/2011-10-01";

	$params = array(
    'AWSAccessKeyId' => AWS_ACCESS_KEY_ID,
    'Action' => "ListMatchingProducts",
    'SellerId' => MERCHANT_ID,
    'SignatureMethod' => "HmacSHA256",
    'SignatureVersion' => "2",
    'Timestamp'=> gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time()),
    'Version'=> "2011-10-01",
    'MarketplaceId' => MARKETPLACE_ID,
    'Query' => $query);

	$url_parts = array();
	
	foreach (array_keys($params) as $key) $url_parts[] = $key . "=" . str_replace('%7E', '~', rawurlencode($params[$key]));
	
	sort($url_parts);

	$url_string = implode("&", $url_parts);
	
	$string_to_sign = "{$method}\n{$host}\n{$uri}\n" . $url_string;

	$signature = hash_hmac("sha256", $string_to_sign, AWS_SECRET_ACCESS_KEY, TRUE);

	$signature = urlencode(base64_encode($signature));

	$url = $base_url . '?' . $url_string . "&Signature=" . $signature;

	return $url;

}

function getASIN($file) {
	
	global $out_dir, $a_headers, $partial;

	$output = fopen($out_dir . $_FILES['userfile']['name'], "w");
	
	fputcsv($output, $a_headers);
	
	if (!is_null($partial)) foreach ($partial as $part) fputcsv($output, $part);
	
	foreach ($file as $r => $row) { 
	
		$amazon[$r]['ASIN'] = queryAmazon(trim($row['UPC\EAN\ISBN\VIN']));
		
		$amazon[$r]['SKU'] = $row['Part Number'];
		
		$amazon[$r]['Price'] = $row['Fixed Price'];
		
		$amazon[$r]['Quantity'] = $row['Quantity To List'];
		
		$amazon[$r]['Weight'] = $row['Weight Major'];
		
		fputcsv($output, $amazon[$r]);
		
		sleep(5);
		
	}
	
	fclose($output);
	
}

function queryAmazon($query) {
	
	$url = makeString($query);
	
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
	$response = curl_exec($ch);

	$xml = simplexml_load_string($response);
	
	$result = @$xml->ListMatchingProductsResult->Products->Product->Identifiers->MarketplaceASIN->ASIN;
	
	return (empty($result)) ? 0 : $result;

}

/*function output($file) {

	global $out_dir, $a_headers;

	$output = fopen($out_dir . $_FILES['userfile']['name'], "w");
	
	fputcsv($output, $a_headers);
	
	foreach ($file as $row) fputcsv($output, $row);
	
	fclose($output);
	
	echo "<p>Success! Your file is located in the following directory: {$out_dir}</p>";

}*/

}

{# VARiABLES

$form = <<<EOT
				<br />
				<center><h2>Choose file to upload</h2>
				<br />
				<form enctype="multipart/form-data" action="{$_SERVER['PHP_SELF']}" method="POST">
				<input type="hidden" name="MAX_FILE_SIZE" value="200000000" />
				<input name="userfile" type="file" />
				<input type="submit" value="submit" />
				</form>
				</center>
EOT;

$source = (empty($_FILES)) ? NULL : fopen($_FILES['userfile']['tmp_name'], "r");

$out_dir = "//orw-file-server/shared/Employees/Jeff/amazon/";

$a_headers = ['ASIN', 'SKU', 'Price', 'Quantity', 'Weight'];

$partial = (!empty($_FILES) && file_exists($out_dir . $_FILES['userfile']['name'])) ? getProcessed() : NULL;

}

{# MAiN

if (empty($_FILES)) echo $form;

else {

$file = getFile($source);

getASIN($file);

}

}

?>

</body>
</html>