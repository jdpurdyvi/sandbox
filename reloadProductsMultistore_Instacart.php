<?php
	/*
	 * reloadProductsMultistore_Reporting.php
	 * 
	 * Included inline from reloadProductsMultistore.php
	 */

	function reloadProductsMultistore_Instacart()
	{
		$response=array(
			'ERRORS'=>array(),
			'NOTES'=>array(),
			'STATUS'=>'FAIL',
			'WARNINGS'=>array()
		);
		
		$query=
'SELECT
	`catalog`.`upc` AS \'upc_ean\',
	`catalog`.`item_desc` AS \'item_name_32\',
	CASE WHEN (`itemTableExpandedText`.`flyerDescription`=\'NULL\' OR `itemTableExpandedText`.`flyerDescription` IS NULL) THEN `catalog`.`item_desc` ELSE `itemTableExpandedText`.`flyerDescription` END AS \'item_name_extended\',
	CASE WHEN (`catalog`.`dept` IN (5,13) AND `catalog`.`upc` LIKE \'0020%\') THEN \'lb\'
		WHEN (`catalog`.`size` IS NULL OR `catalog`.`size`=\'\') THEN \'each\' 
		ELSE `size` END AS \'size\',
	`catalog`.`retail` AS \'cost_price_per_unit\',
	CASE 
		WHEN (`catalog`.`dept` IN (5,13) AND `catalog`.`upc` LIKE \'0020%\') THEN \'lb\'
		WHEN `catalog`.`weighed`=1 THEN \'lb\' 
		ELSE \'each\' END AS \'price_unit\',
	CASE WHEN `catalog`.`taxable`=1 THEN \'Y\' ELSE \'N\' END AS \'taxable_a\',
	`brands`.`brand_name` AS \'brand_name\',
	`departments`.`dept_name` AS \'department_name\',
	CASE WHEN `catalog`.`active`=1 THEN \'true\' ELSE \'false\' END AS \'available\',
	CASE WHEN (`catalog`.`promoActive`=1 AND `catalog`.`promoStart`<=NOW() AND `catalog`.`promoEnd`>=DATE(NOW()) AND `catalog`.`memberspecial`!=1 AND `catalog`.`attributes`&32=0) THEN `catalog`.`promoRetail` ELSE \'\' END AS \'sale_price\',
	CASE WHEN (`catalog`.`promoActive`=1 AND `catalog`.`promoStart`<=NOW() AND `catalog`.`promoEnd`>=DATE(NOW()) AND `catalog`.`memberspecial`!=1 AND `catalog`.`attributes`&32=0) THEN CONCAT(YEAR(`catalog`.`promoStart`), LPAD(MONTH(`catalog`.`promoStart`),2,\'0\'), LPAD(DAYOFMONTH(`catalog`.`promoStart`),2,\'0\')) ELSE \'\' END AS \'promo_start_date\',
	CASE WHEN (`catalog`.`promoActive`=1 AND `catalog`.`promoStart`<=NOW() AND `catalog`.`promoEnd`>=DATE(NOW()) AND `catalog`.`memberspecial`!=1 AND `catalog`.`attributes`&32=0) THEN CONCAT(YEAR(DATE_ADD(`catalog`.`promoEnd`, INTERVAL 1 DAY)), LPAD(MONTH(DATE_ADD(`catalog`.`promoEnd`, INTERVAL 1 DAY)),2,\'0\'), LPAD(DAYOFMONTH(DATE_ADD(`catalog`.`promoEnd`, INTERVAL 1 DAY)),2,\'0\'))	ELSE \'\' END AS \'promo_start_end\',
	CASE WHEN `catalog`.`organic`=1 THEN \'Y\' ELSE \'N\' END AS \'organic\',
	CASE WHEN `catalog`.`numflag`&512=512 THEN \'Y\' ELSE \'N\' END AS \'vegan\',
	CASE WHEN `catalog`.`glutenFree`=1 THEN \'Y\' ELSE \'N\' END AS \'gluten free\',
	CONCAT(
		CASE WHEN `catalog`.`numflag`&1=1 THEN \'[Local] \' ELSE \'\' END,
		CASE WHEN `catalog`.`numflag`&256=256 THEN \'[Fair Trade Certified] \' ELSE \'\' END,
		CASE WHEN `catalog`.`gmo`=3 THEN \'[Verified Non-GMO] \' ELSE \'\' END) AS \'Item Details\',
	CASE WHEN `instacart_filters`.`weighed_option`=1 THEN \'1/4 LB\' ELSE \'\' END AS \'Weight Option\'
FROM `wedgepos`.`catalog`
JOIN `wedgepos`.`brands` ON `catalog`.`brand`=`brands`.`brand_id`
JOIN `wedgepos`.`departments` ON `catalog`.`dept`=`departments`.`dept_no`
JOIN `wedgepos`.`lastSold` ON CONVERT(`catalog`.`upc` USING latin1)=`lastSold`.`upc`
JOIN `wedgepos`.`itemTableExpandedText` ON CONVERT(`catalog`.`upc` USING latin1)=`itemTableExpandedText`.`upc`
JOIN `shelfaudit`.`instacart_filters` ON `catalog`.`upc`=`instacart_filters`.`upc`
WHERE 1=1
	AND `instacart_filters`.`exclude`=0
	AND `catalog`.`store`=0
	AND `lastSold`.`datetime`>\''.strftime("%F", strtotime("-9 WEEKS")).'\'
	AND `catalog`.`item_desc` NOT LIKE \'Offsite:%\'
	AND `catalog`.`item_desc` NOT LIKE \'open\'
	AND `catalog`.`item_desc` NOT LIKE \'MM:%\'
	AND `catalog`.`item_desc` NOT LIKE \'KB:%\'
	AND `catalog`.`item_desc` NOT LIKE \'W:%\'
	AND `catalog`.`retail`>0
	AND `catalog`.`retail`!=0.01
	AND `catalog`.`dept` NOT IN (9, 14, 15, 22)
	AND `catalog`.`section` NOT IN (102,498)
	AND `catalog`.`active`=1
	AND `catalog`.`deleted`=0
ORDER BY `dept_no`, `brand_name`, `catalog`.`upc`';

		$result=\settings::$link->query($query);
		if ($result) {
			$rowset=$result->fetchAll(PDO::FETCH_ASSOC);
			if (count($rowset)>0) {
				$filename=strftime("%m%d%Y_Minneapolis_WedgeCoop.csv");
				/*
				 * Ugh... Instacart wants "" instead of empty columns. So, 
				 * have PHP quote all empty columns. Then, go back and delete
				 * the extra space, because that's no good either.
				 */
				$out=fopen('/tmp/'.$filename, 'w+');
				fwrite($out, '"upc_ean","item_name_32","item_name_extended","size","cost_price_per_unit","price_unit","taxable_a","brand_name","department_name","available","sale_price","promo_start_date","promo_start_end","organic","vegan","gluten free","Item Details","Weight Option"
');
				$quoted_rowset=array();
				foreach($rowset as $row) {
					foreach ($row as $key=>$val) {
						$quoted_row[$key]=(empty($val)?' ':$val);
					}
					fputcsv($out, $quoted_row, ',', '"');
				}
				fclose($out);
				$quoted_csv=file_get_contents('/tmp/'.$filename);
				$cleaned_csv=str_replace('" "', '""', $quoted_csv);
				$csv_out=file_put_contents('/tmp/'.$filename, $cleaned_csv);
				
				$copy=copy('/tmp/'.$filename, '/mnt/BackupSomewhere/Instacart/CSV/'.$filename);
				
				
				$conn=ssh2_connect('sftp.instacart.com', 22);
				ssh2_auth_password($conn, 'the-wedge-co-op-catalog', 'PASSWORD');
				$sftp = ssh2_sftp($conn);
				$sftp_out=file_put_contents('ssh2.sftp://'.$sftp.'/inventory-files/363-minneapolis-wedge-co-op/'.$filename, $cleaned_csv);
				
				$response['STATUS']='SYNC';
				array_push($response['NOTES'], count($rowset));
			} else {
				$response['STATUS']='FAIL';
				array_push($response['NOTES'], 'Zero data for Instacart');
			}
		} else {
			$response['STATUS']='FAIL';
			array_push($response['NOTES'], 'Failure to pull for Instacart');
			array_push($response['ERRORS'], print_r(\settings::$link->errorInfo(),1));
		}
		
		return $response;
	}
