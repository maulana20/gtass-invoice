<?php
require_once 'Classes/PHPExcel/IOFactory.php';

class PHPExcelModel
{
	protected $objphpxls;
	
	function __construct($file)
	{
		try {
			$objphpxls = PHPExcel_IOFactory::load('file/' . $file);
		} catch (Exception $e) {
			$f = fopen('log/phpexcel_error.txt', 'w');
			fwrite($f, date('d M Y H:i:s') . ' ' . $e->getMessage() . "\r");
			fclose($f);
		}
		$this->objphpxls = $objphpxls;
	}
	
	function getTitle()
	{
		$objphpxls = &$this->objphpxls;
		
		$result = array();
		
		//An excel file may contains many sheets, so you have to specify which one you need to read or work with.
		$sheet = $objphpxls->getSheet(0);
		//It returns the highest number of rows
		$total_rows = $sheet->getHighestRow();
		//It returns the highest number of columns
		$highest_column = $sheet->getHighestColumn();
		
		// UNTUK JUDUL KARENA ROW PERTAMA ITU JUDUL
		$single_row = $sheet->rangeToArray('A1:' . $highest_column . '1', NULL, TRUE, FALSE);
		$title_list = array();
		$result = $single_row[0]; // array(No,Date,Date Trans,Bank,Credit,Debit,Balance,Desc,Flag);
		
		return $result;
	}
	
	function getHistoryData()
	{
		$objphpxls = &$this->objphpxls;
		
		$result = array();
		
		//An excel file may contains many sheets, so you have to specify which one you need to read or work with.
		$sheet = $objphpxls->getSheet(0);
		//It returns the highest number of rows
		$total_rows = $sheet->getHighestRow();
		//It returns the highest number of columns
		$highest_column = $sheet->getHighestColumn();
		
		// UNTUK JUDUL KARENA ROW PERTAMA ITU JUDUL
		$single_row = $sheet->rangeToArray('A1:' . $highest_column . '1', NULL, TRUE, FALSE);
		$title_list = array();
		$title_list = $single_row[0]; // array(No,Date,Date Trans,Bank,Credit,Debit,Balance,Desc,Flag);
		
		$index = -1;
		for($row =2; $row <= $total_rows; $row++) {
			$index++;
			//This line of code selects range of the cells like A1:D1
			$single_row = $sheet->rangeToArray('A' . $row . ':' . $highest_column . $row, NULL, TRUE, FALSE);
			
			// BUAT DATA ISI SESUAI DENGAN JUDULNYA
			foreach ($single_row[0] as $k => $v) {
				$result[$index][$title_list[$k]] = $v;
				if ( in_array($title_list[$k], array('Date', 'Time', 'Booking Date', 'Time Depart', 'Time Arrive')) && is_float($v) ) {
					try {
						$date_obj = PHPExcel_Shared_Date::ExcelToPHPObject($v);
					} catch (Exception $e) {
						$f = fopen('log/phpexcel_error.txt', 'w');
						fwrite($f, date('d M Y H:i:s') . ' ' . $e->getMessage() . "\r");
						fclose($f);
						exit();
					}
					if ($title_list[$k] == 'Date') {
						$result[$index][$title_list[$k]] = $date_obj->format('d-M-Y');
					} else if ($title_list[$k] == 'Time') {
						$result[$index][$title_list[$k]] = $date_obj->format('H:i:s');
					} else {
						$result[$index][$title_list[$k]] = $date_obj->format('d-M-Y H:i:s');
					}
				}
				if ($title_list[$k] == 'Trans┬áId' && is_float($v)) $result[$index][$title_list[$k]] = number_format($v,0,'.','');
			}
		}
		
		return $result;	
	}
}
