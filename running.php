<?php
include_once 'Startup.php';
require_once 'GTASSModel.php';
require_once 'PHPExcelModel.php';

function logRes($path, $result)
{
	$f = fopen($path, 'a');
	fwrite($f, date('Ymd H:i:s') . " " . $result . "\r\n");
	fclose($f);
}

function moveFile($file_name, $ext)
{
	shell_exec('echo Y| COPY file\\' . $file_name . ' file_ext\\' . rtrim($file_name, '.' . $ext) . date('YmdHis') . '.' . $ext . ' /Y');
	shell_exec('echo Y| DEL file\\' . $file_name . ' /Q');
}

function getKonsorsiumName($index)
{
	$konsorsium_list = array(1 => 'LION', 2 => 'SRIWIJAYA', 3 => 'CITILINK', 4 => 'GARUDAAPI', 5 => 'XPRESS', 6 => 'TRANSNUSA', 7 => 'TRIGANA', 8 => 'KAI', 9 => 'HOTEL', 10 => 'PELNI', 11 => 'BUS');
	return $konsorsium_list[$index];
}

// PILIH BANKNYA
echo "**************************************" . "\n";
echo "* Konsorsium airlines yang di pilih  *" . "\n";
echo "**************************************" . "\n";
echo "* 1. LION                            *" . "\n";
echo "* 2. SRIWIJAYA                       *" . "\n";
echo "* 3. CITILINK                        *" . "\n";
echo "* 4. GARUDA API                      *" . "\n";
echo "* 5. XPRESS                          *" . "\n";
echo "* 6. TRANSNUSA                       *" . "\n";
echo "* 7. TRIGANA                         *" . "\n";
echo "**************************************" . "\n";
echo "> ";
$handle = fopen ("php://stdin","r");
$konsorsium_choice = trim(fgets($handle));
if (empty($konsorsium_choice)) {
	echo "result : Konsorsium harus di pilih tidak boleh kosong !";
	logRes('log/gtass_log.txt', "Konsorsium harus di pilih tidak boleh kosong !");
	exit();
}
if (! in_array($konsorsium_choice, array(1,2,3,4,5,6,7)) ) {
	echo "result : Konsorsium yang di pilih tidak ada !";
	logRes('log/gtass_log.txt', "Bank yang di pilih tidak ada !");
	exit();
}
$konsorsium_name = getKonsorsiumName($konsorsium_choice);

if (empty($konsorsium_name)) {
	echo "result : Konsorsium yang di pilih kosong !";
	logRes('log/gtass_log.txt', "Bank yang di pilih kosong !");
	exit();
}

// MASUKAN NAMA FILE NYA
echo "> Nama File (" . $konsorsium_name . ") : " . "\n";
echo "> ";
$handle = fopen ("php://stdin","r");
$file_name = trim(fgets($handle));
if (empty($file_name)) {
	echo "result : Nama file tidak boleh kosong !";
	logRes('log/gtass_log.txt', "Nama file tidak boleh kosong !");
	exit();
}

if (file_exists('file/' . $file_name)) {
	
	// HANYA FORMAT EXCEL AJA YANG BOLEH
	$ext = pathinfo($file_name, PATHINFO_EXTENSION);
	if ( !in_array($ext, array('xls','xlsx')) ) {
		echo "result : Format file tidak dapat di gunakan !";
		logRes('log/gtass_log.txt', "Format file tidak dapat di gunakan !");
		exit();
	}
	
	// OPEN EXCEL HARUS SUPPORT DENGAN FORMAT PHPEXCEL DARI LIBRARY
	try {
		$phpexcel = new PHPExcelModel($file_name);
	} catch (Exception $e) {
		echo $e->getMessage();
		exit();
	}
	
	// TAMPILKAN DATA UNTUK DI COCOKAN SEBAGAI ACUAN PENYESUAIAN DATA SEBELUM DI PROSES SEPERTI JUDUL DAN DATA ISINYA SESUAI
	$title_list = array();
	$title_list = $phpexcel->getTitle();
	
	if (empty($title_list)) {
		echo "result : (" . $konsorsium_name . ") Format tidak mendukung !";
		logRes('log/gtass_log.txt', "(" . $konsorsium_name . ") Format tidak mendukung !");
		exit();
	}
	if ($title_list[1] != 'Date' || $title_list[4] != 'Booking Code' || $title_list[15] != 'Ticket Number') {
		echo "result : Format title tidak mendukung !";
		logRes('log/gtass_log.txt', "Format title tidak mendukung !");
		exit();
	}
	if ($title_list[14] != 'Airline') {
		echo "result : (" . $konsorsium_name . ") Format title tidak mendukung !";
		logRes('log/gtass_log.txt', "(" . $konsorsium_name . ") Format title tidak mendukung !");
		exit();
	}
	
	echo implode('|', $title_list) . "\n";
	
	$list = array();
	$list = $phpexcel->getHistoryData();
	foreach ($list as $k => $v) {
		$record = array();
		foreach ($title_list as $key => $val) {
			$record[] = $v[$val];
		}
		echo implode('|', $record) . "\n";
	}
	
	// PERINTAH UNTUK PROSES
	echo "\n";
	echo "> Simpan data ? (Y/N)" . "\n";
	echo "> ";
	while (true) {
		// LANJUT MANG
		$handle = fopen ("php://stdin", "r");
		$cmd = strtoupper(trim(fgets($handle)));
		
		// TIDAK DI PROSES
		if ( in_array($cmd, array('QUIT', 'quit')) ) exit();
		if ( in_array($cmd, array('n', 'N')) ) {
			echo "result : (" . $konsorsium_name . ") Data tidak simpan !";
			logRes('log/gtass_log.txt', "(" . $konsorsium_name . ") Data tidak simpan !");
			break;
		}
		
		// PROSES DATA
		if ( in_array($cmd, array('y', 'Y')) ) {
			
			// MASUKAN KODE COA UNTUK BISA DI OLAH DI ARAHKAN KEMANA
			echo "> Masukan Code Customer (AGENT VERSA OR MITRA)" . "\n";
			echo "> ";
			$handle = fopen ("php://stdin","r");
			$customer_code = trim(fgets($handle));
			if (empty($customer_code)) {
				echo "result : Code Customer kosong !";
				logRes('log/gtass_log.txt', "Kode akun kosong !");
				break;
			}
			if (strlen($customer_code) != 8) {
				echo "result : Code Customer harus sesuai (8 karakter) !";
				logRes('log/gtass_log.txt', "Code Customer harus sesuai (8 karakter) !");
				break;
			}
			
			// KIRIM DATA KE GTASS MULAI
			$gtass = new GTASSModel();
			$gtass->start($params);
			
			// CEK COA AKUNNYA ADA TIDAK
			$customer_data = array();
			$customer_data = $gtass->getCustomerData($customer_code);
			if (empty($customer_data) || empty($customer_data['code'])) {
				echo "result : Code Customer tidak ada !";
				logRes('log/gtass_log.txt', "Code Customer tidak ada !");
				$gtass->logoutClient();
				break;
			}
			
			foreach ($list as $k => $v) {
				// UNTUK RECORD BENTUKAN ARRAY SAYA SUPAYA TAMPIL DI LAYAR
				$record = array();
				foreach ($title_list as $key => $val) {
					$record[] = $v[$val];
				}
				
				// CEK DATA TERSEBUT UNTUK DI PILAH
				$result = NULL;
				$valid = false;
				
				if ($konsorsium_choice == 1) { // Lion Air 2
					if ($v['Airline'] != 'Lion Air') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Lion Air in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 2) { // Sriwijaya API
					if ($v['Airline'] != 'Sriwijaya API') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Sriwijaya API in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 3) { // Citilink API
					if ($v['Airline'] != 'Citilink API') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Citilink API in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 4) { // Garuda API
					if ($v['Airline'] != 'Garuda API') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Garuda API in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 5) { // Xpress Air
					if ($v['Airline'] != 'Xpress Air') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Xpress Air in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 6) { // Transnusa API
					if ($v['Airline'] != 'Transnusa API') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Transnusa API in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				} else if ($konsorsium_choice == 7) { // Trigana API
					if ($v['Airline'] != 'Trigana API') {
						$result = implode('|', $record) . "(" . $konsorsium_name . ") Must Trigana API in column airline" . "\r\n";
						sleep(5);
					} else {
						$valid = true;
					}
				}
				
				// SIMPAN DATA
				if ($valid) {
					
					// PROSES MEMASUKAN DATA
					$class_type = '';
					$class_code = substr($v['Class'], 0, 1);
					$flight_code = substr($v['Flight'], 0, 10);
					$route_list = array();
					$is_domestic = 1;
					$oneway = 1;
					$qty = 1;
					
					$air_code = $ticket_three_code = '';
					if ($konsorsium_choice == 1) {
						$air_code = 'A00013'; // Lion Air 2
						$ticket_three_code = '990'; // Lion Air 2
					} else if ($konsorsium_choice == 2) {
						$air_code = 'A00003'; // Sriwijaya Air
						$ticket_three_code = '977'; // Sriwijaya Air
					} else if ($konsorsium_choice == 3) {
						$air_code = 'A00004'; // Citilink Air
						$ticket_three_code = ''; // Citilink Air
					} else if ($konsorsium_choice == 4) {
						$air_code = 'A00001'; // Garuda API
						$ticket_three_code = '126'; // Garuda API
					} else if ($konsorsium_choice == 5) {
						$air_code = 'A00009'; // Xpress Air
						$ticket_three_code = '999'; // Xpress Air
					} else if ($konsorsium_choice == 6) {
						$air_code = 'A00012'; // Transnusa Air
						$ticket_three_code = '000'; // Transnusa Air
					} else if ($konsorsium_choice == 7) {
						$air_code = 'A00010'; // Trigana Air
						$ticket_three_code = '000'; // Trigana Air
					}
					
					$route_list = explode('-', $v['Route']);
					$time_depart = strtotime($v['Time Depart']);
					$time_arrive = strtotime($v['Time Arrive']);
					$city_depart = $route_list[0];
					$city_arrive = $route_list[1];
					$contact_list = explode('.', $v['Contact']);
					$contact_title = strtoupper($contact_list[0]);
					$contact_name = trim($contact_list[1]);
					
					$data = array();
					
					// CREATE TICKET
					$data['booking_code'] = substr($v['Booking Code'], 0, 7);
					$data['booking_date'] = strtotime($v['Booking Date']);
					$data['issued_date'] = strtotime($v['Date']);
					$data['oneway'] = 1;
					$data['qty'] = $qty;
					$data['is_domestic'] = $is_domestic;
					$data['air_code'] = $air_code;
					$data['contact_title'] = $contact_title;
					$data['contact_name'] = $contact_name;
					$data['ticket_three_code'] = $ticket_three_code;
					$ticket_number = (string) $v['Ticket Number'];
					$ticket_number = ltrim($v['Ticket Number'], $ticket_three_code);
					$ticket_number = substr($ticket_number, 0, 11);
					if ( empty($ticket_number) || strtolower($ticket_number) == 'confirm' ) $ticket_number = $data['booking_code'];
					$data['ticket_number'] = $ticket_number;
					
					// INCLUDE TICKET SCHEDULE
					$data['schedule']['time_depart'] = $time_depart;
					$data['schedule']['time_arrive'] = $time_arrive;
					$data['schedule']['city_depart'] = $city_depart;
					$data['schedule']['city_arrive'] = $city_arrive;
					$data['schedule']['class_code'] = $class_code;
					$data['schedule']['class_type'] = $class_type;
					$data['schedule']['flight_code'] = $flight_code;
					
					// INCLUDE TICKET PRICE
					$data['fare']['basic'] = $v['Basic'];
					$data['fare']['tax'] = $v['Tax'];
					$data['fare']['total'] = $v['Publish'];
					// jika ada real_nta = Real NTA maka dapat komisi di GTASS
					$data['fare']['real_nta'] = ( in_array($konsorsium_choice, array(1,4,5,6,7)) ) ? $v['Real NTA'] : $v['Publish'];
					if ($konsorsium_choice == 1) $data['fare']['real_nta'] -= 2000; // UP 2,000 di ambil sama versatech 
					
					$remark1 = $data['booking_code'] . ' ' . $konsorsium_name . ' ' . $data['ticket_number'];
					$remark1 = substr($remark1, 0, 100);
					
					$gtass = new GTASSModel();
					$gtass->start($params);
					
					// CHECK SUDAH ADA INVOICENYA
					// NOTED :
					// 1. Satu invoice untuk satu kode booking
					// 2. Tiket untuk keseluruhan dalam satu kode booking
					
					$is_already_invoice = true;
					$is_already_invoice = $gtass->isAlreadyInvoice($data['issued_date'], $remark1); // BY DATE SEARCH BY REMARK1
					if ($is_already_invoice) {
						$result = implode('|', $record) . " Is Already Invoice" . "\r\n";
						sleep(5);
					} else {
						// CHECK SUDAH ADA TICKET SEBELUMNYA
						$is_already_tiket = true;
						$is_already_tiket = $gtass->isAlreadyResTicket($data['issued_date'], $data['booking_code']); // BY DATE SEARCH BY KODE BOOKING
						if ($is_already_tiket) {
							$result = implode('|', $record) . " Is Already Ticket" . "\r\n";
							sleep(5);
						} else {
							// ADD TICKET
							$gtass->addReservationTicket($data, $konsorsium_choice);
							
							// BUAT INVOICE JIKA SUDAH PROSES MAKA RETURN CONFIRM AKHIR TRUE
							$confirm_invoice = false;
							$confirm_invoice = $gtass->addInvoice($data, $customer_data, $remark1);
							
							$result = implode('|', $record) . (($confirm_invoice == true) ? " Done" : " Not Proccess") . "\r\n";
							sleep(10);
						}
					}
				}
				
				// TAMPILKAN PROSES
				echo $result;
				
				// BACKUP RESULT PROSES
				$f = fopen('file_ext/' . rtrim($file_name, '.' . $ext) . '.txt', 'a');
				fwrite($f, $result);
				fclose($f);
			}
			
			// PIDAHKAN FILE NYA JIKA SUDAH BERHASIL (PENANDA)
			sleep(2);
			moveFile($file_name, $ext);
			
			// BUAT TANDAIN AJA
			echo "result : Data proses !";
			logRes('log/gtass_log.txt', "Data proses !");
			$gtass->logoutClient();
			break;
		}
		echo "> ";
	}
} else {
	echo "result : File tidak di temukan !";
	logRes('log/gtass_log.txt', "File tidak di temukan !");
	exit();
}
