<?php
require_once 'Zend/Http/Client.php';

abstract class HostToHostIOModel
{
	protected $url;
	protected $client;
	
	abstract protected function loginClient($username, $password);
	abstract protected function logoutClient();
	
	//========================================
	// PRIVATE-FUNCTION
	//========================================
	//==========================================
	// ADD/EDIT/DELETE FUNCTION
	//==========================================
	protected function logResponse($file, $response)
	{
		$f = fopen($file, 'w');
		fwrite($f, $response->getHeadersAsString() . "\n" . $response->getBody());
		fclose($f);
	}

	public function start($data)
	{
		$this->curloptions = array(
			CURLOPT_SSL_VERIFYPEER => false,
		);
		$this->url = $data['url'];
		$this->createClient();
		$this->loginClient($data['username'], $data['password']);
		$this->saveClient();
	}
	
	protected function createClient()
	{
		$this->client = new Zend_Http_Client($this->url);
		$config = array('timeout' => 60,
						'ssltransport' => 'sslv3',
						'keepalive' => true,
					    'adapter'      => 'Zend_Http_Client_Adapter_Curl',
    					'persistent' => true,
						'curloptions' => $this->curloptions,
//						'useragent' => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36',
		);
		$this->client->setConfig($config);
		$this->client->setCookieJar();
		$this->client->setHeaders("Connection", "keep-alive");
	}
	
	protected function setClientTimeout($timeout = 60)
	{
		$config = array('timeout' => $timeout);
		$this->client->setConfig($config);
	}
	
	public function stop()
	{
		$this->logoutClient();
		$this->deleteClient();
	}

	protected function deleteClient()
	{
		if (file_exists('Interface/gtass.dat')) {
			unlink('Interface/gtass.dat');
		}
	}
	
	protected function saveClient()
	{
		$s = serialize($this->client);
		file_put_contents('Interface/gtass.dat', $s);
	}
	
	protected function getClientFromDatabase($file_name, $h2h_id)
	{
		$file_is_exists = file_exists('Interface/gtass.dat');
		if ($file_is_exists) {
			try {
				$s = file_get_contents('Interface/gtass.dat');
				$this->client = unserialize($s);
			} catch (Exception $e) {
				$s = false;
			}
		} else {
			$s = false;
		}
		
		if ($s === false) return false; else return true;
		
	}
	
	//========================================
	// COMPARE DATA FUNCTION RETURN BOOLEAN
	//========================================

	//========================================
	// GET DATA FUNCTION RETURN DATA
	//========================================

}

class GTASSModel extends HostToHostIOModel
{	
	function loginClient($username, $password)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['username'] = $username;
		$data['password'] = $password;
		$client->setUri($host . '/login');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSlogin.html", $response);
		
		$body = stristr($result, 'alert alert-danger alert-block');
		$body = stristr($body, 'validation-summary-errors');
		$body = stristr($body, '<li');
		$body = stristr($body, '>');
		$matches = substr($body, 1, strpos($body, '</li', 1)-1);

		if (strtolower($matches) == 'this user is logged on.') {
			$client->resetParameters();
			$data = array();
			$data['username'] = $username . '/force';
			$data['password'] = $password;
			$client->setUri($host . '/login');
			$client->setParameterPost($data);
			try {
				$response = $client->request(Zend_Http_Client::POST);
				$result = $response->getBody();
			} catch (Exception $e) {
				echo $e->getMessage();
				logRes("log/gtass_error.txt", $e->getMessage());
				exit();
			}
			$this->logResponse("log/GTASSloginforce.html", $response);
		}
	}
	
	function logoutClient()
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/logout');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSLogout.html", $response);
	}
	
	function isSessionTimeout()
	{
		return true;
	}
		
	function addGeneralCb($data)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/api/file/template/general-cb/html');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSGeneralCb.html", $response);
		
		
		/*$data = array();
		$data['act'] = 'add';
		$data['amount'] = 0;
		$data['coaCode'] = '111501'; // option
		$data['code'] = '<AUTO>';
		$data['currCode'] = 'IDR';
		$data['date'] = '2018-07-25'; // option
		$data['descr'] = 'test';
		$data['issueBy'] = 3; // option
		$data['locationId'] = 1; // mandatory : Pusat
		$data['mark'] = 'A';
		$data['rate'] = 1;
		$data['type'] = 'D'; // option
		$data['ChequeNo'] = '';
		$data['chequeDate'] = '';
		*/
		$client->resetParameters();		
		$json = json_encode($data);
		$client->setUri($host . '/api/general-cashbank/update?act=add');
		$client->setRawData($json, 'application/json');
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSAddGeneralCb.html", $response);
	}
	
	function addDepositAgentCb($res, $coa_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$client->setUri($host . '/api/file/template/deposit-subagent-cb/html');
		try {
			$response = $client->request();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$this->logResponse("log/GTASSDepositAgentCb.html", $response);
		
		$data = array();
		$data['act'] = 'add';
		$data['amount'] = $res['amount'];
		$data['coaCode'] = $coa_code;
		$data['code'] = '<AUTO>';
		$data['currCode'] = 'IDR';
		$data['custCode'] = 'C0100051'; // SUB AGENT VERSA
		$data['date'] = date('Y-m-d', $res['date']);
		$data['descr'] = substr($res['descr'], 0, 100);
		$data['issueBy'] = 3; // PT (Putut)
		$data['locationId'] = 1; // Pusat
		$data['rate'] = 1;
		$data['type'] = 'D'; // Mandatory
		$client->resetParameters();
		$json = json_encode($data);
		$client->setUri($host . '/api/depo-sa-cb/update?act=add');
		$client->setRawData($json, 'application/json');
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", $res_json['message']);
		}
		
		$this->logResponse("log/GTASSAddDepositAgentCb.html", $response);
	}
	
	function addReservationTicket($res, $konsorsium_choice)
	{
		// Meliputi menu : Operation - Input - Reservation - Ticket
		// 1. Masukan data booking yang sudah tiket
		// 2. Masukan reservasi detailnya
		// 3. Masukan data penumpang beserta tiket dan harganya
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = '';
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/ticket-trans/list');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'open menu ticket ' . $e->getMessage());
			exit();
		}
		
		$this->logResponse("log/GTASSReservationTicketList.html", $response);
		
		// DONE
		$client->resetParameters();
		$data = array();
		$data['act'] = 'add';
		$data['adtQty'] = $res['qty'];
		$data['airCode'] = $res['air_code']; // A00013 => Lion 2, 
		$data['bookBy'] = 'M0003'; // PUTUT
		$data['bookCode'] = $res['booking_code'];
		$data['bookDate'] = date('Y-m-d', $res['booking_date']); // 2018-08-20
		$data['chdQty'] = 0;
		$data['currCode'] = 'IDR';
		$data['dom'] = $res['is_domestic']; // dom : domestic
		$data['infQty'] = 0;
		$data['issuedBy'] = 'M0003'; // PUTUT
		$data['issuedDate'] = date('Y-m-d', $res['issued_date']);
		$data['locationId'] = 1; // pusat
		$data['oneWay'] = $res['oneway']; // choice 1 => oneway
		$data['referral'] = 'ticket-trans'; // MANDATORY
		$data['tourCode'] = NULL;
		$data['type'] = 'DP';
		$json = json_encode($data);
		$client->setUri($host . '/api/ticket-trans/update?act=add');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'input tiket ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'input tiket ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSReservationTicketAdd.html", $response);
		// succes {"count":1,"errorNo":0,"success":true,"message":"Success insert record.","data":{"id":307,"invCode":null,"lgCode":null,"tourCode":null,"tourQuoDetLineNo":null,"oneWay":true,"dom":true,"typeCode":"TICKD","type":"DP","supCode":"A00013","airCode":"A00013","bookDate":"2018-08-20T00:00:00","bookBy":"M0003","issuedDate":"2018-08-20T00:00:00","issuedBy":"M0003","bookCode":"DICOBA","route":null,"flightDate":null,"adtQty":1,"chdQty":0,"infQty":0,"currCode":"IDR","publishFare":0.0,"totalNta":0.0,"totalDisc":0.0,"totalExtraDisc":0.0,"totalSerFee":0.0,"idc":0,"referral":"ticket-trans","locationId":1,"companyId":1,"status":true,"createdBy":9,"createdDate":"2018-08-20T10:49:17.9696586+07:00","changedBy":9,"changedDate":"2018-08-20T10:49:17.9696586+07:00"}}
		
		$res_json = json_decode($result, true);
		$ticket_code = $res_json['data']['id'];
		
		// DONE
		$client->resetParameters();
		$data = array();
		$data['act'] = 'add';
		$data['depAirport'] = $res['schedule']['city_depart']; // CGK
		$data['arrAirport'] = $res['schedule']['city_arrive']; // BGR
		$data['depTime'] = date('H:i', $res['schedule']['time_depart']); // 12:00
		$data['arrTime'] = date('H:i', $res['schedule']['time_arrive']); // 13:00
		$data['classCode'] = $res['schedule']['class_code']; // X
		$data['classType'] = $res['schedule']['class_type']; // nb E : Economy, B : Bisnis, F : First Class
		$data['flightDate'] = date('Y-m-d', $res['schedule']['time_depart']); // 2018-08-20
		$data['flightNo'] = $res['schedule']['flight_code']; // JT XXX
		$data['id'] = $ticket_code; // 307 : id by return add ticket
		$data['idc'] = $ticket_code; // 307 : id by return add ticket
		$data['locId'] = 1; // PUSAT
		$data['referral'] = 'ticket-trans'; // MANDATORY
		$json = json_encode($data);
		$client->setUri($host . '/api/ticket-trans/updateroute?act=add');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'input schedule ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'input schedule ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSReservationTicketSchedule.html", $response);
		
		// DONE
		$client->resetParameters();
		$data = array();
		$data['act'] = 'add';
		$data['id'] = $ticket_code;
		$data['basicFare'] = $res['fare']['basic'];
		$data['iwjr'] = $res['fare']['tax'];
		$data['publish'] = $res['fare']['total'];
		$data['nta'] = $res['fare']['real_nta']; // 111000-5000
		$data['agentCom'] = $data['publish'] - $data['nta'];
		$data['bsp'] = $data['disc'] = $data['discP'] = $data['extraDisc'] = $data['extraDiscP'] = $data['fs'] =  $data['incentive'] = $data['insurance'] = $data['issueFee'] = $data['agentComP'] = $data['airportTax'] = $data['ppn'] = $data['ppnP'] = $data['profit'] = $data['serFee'] = 0;
		if ($konsorsium_choice == 3) $data['disc'] = $data['publish'] - $data['nta']; // only citilink
		$data['allowEditPurchase'] = $data['allowEditSales'] = $data['allowShowSales'] = true;
		$data['threeCode'] = $res['ticket_three_code']; // 990 => Lion, 
		$data['tickNo'] = $res['ticket_number']; // by ticket
		$data['title'] = $res['contact_title']; // by contact
		$data['firstName'] = $res['contact_name']; // by contact
		$data['lastName'] = NULL;
		$data['locId'] = 1;
		$data['partTickNo'] = 888;
		$data['paxType'] = 'A';
		$data['referral'] = 'ticket-trans';
		$data['tourCode'] = NULL;
		$json = json_encode($data);
		$client->setUri($host . '/api/ticket-trans/updatedetail?act=add');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'input fare ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'input fare ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSReservationTicketPrice.html", $response);
	}
	
	function addInvoice($res, $customer_data, $remark1)
	{
		// Meliputi menu : Operation - Input - Invoice - General
		// 1. Masukan invoice (pendataan)
		// 2. Pencarian data yang telah di buat tiketnya
		// 3. Sisipkan hasil tiket untuk pembuatan invoice
		// 4. Konfirmasi invoice jika sudah OK
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = '';
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/invoice/list');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'open menu invoice ' . $e->getMessage());
			exit();
		}
				
		$this->logResponse("log/GTASSInvoiceGeneralList.html", $response);
				
		$client->resetParameters();
		$data = array();
		$data['act'] = 'add'; // MANDATORY
		$data['attn'] = ''; // MANDATORY
		$data['code'] = '<AUTO>'; // MANDATORY
		$data['currCode'] = 'IDR'; // MANDATORY
		$data['custCode'] = $customer_data['code']; // COA SUB AGENT VERSA
		$data['custName'] = $customer_data['name']; // COA SUB AGENT VERSA
		$data['custPhone'] = $customer_data['phone']; // COA SUB AGENT VERSA
		$data['date'] = $data['dueDate'] = date('Y-m-d', $res['issued_date']); // 2018-08-20 DATE ISSUED
		$data['deposit'] = $data['extraDisc'] = 0; // MANDATORY
		$data['isDpSubAgent'] = true; // CHOICE BY DEPOSIT AGENT
		$data['isWG'] = false; // CHOICE BY FIT
		$data['locationId'] = 1; // PUSAT
		$data['paxPaid'] = $data['pph23'] = $data['ppn'] = 0; // MANDATORY
		$data['prodCode'] = 'TICKD'; // MANDATORY
		$data['rate'] = 1; // MANDATORY
		$data['referral'] = 'invoice'; // MANDATORY
		$data['remark1'] = $remark1; // DESKRIPSI
		$data['salesBy'] = 'M0003'; // PUTUT
		$data['stamp'] = 0; // MANDATORY
		$data['taxNo'] = 0; // OPTIONAL
		$data['tourCode'] = NULL; // MANDATORY
		$json = json_encode($data);
		$client->setUri($host . '/api/invoice/update?act=add');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'input invoice ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'input invoice ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSInvoiceGeneralAdd.html", $response);
		// success {"count":1,"errorNo":0,"success":true,"message":"Success insert record.","data":{"companyId":1,"code":"0118000428","invCode":null,"locationId":1,"cnEdCode":null,"tourCode":null,"prodCode":"TICKD","date":"2018-08-20T00:00:00","dueDate":"2018-08-20T00:00:00","salesBy":"M0003","custCode":"C0100051","custName":"SUB AGEN VERSA","custPhone":"62 21","attn":"","isCredit":false,"isDpSubAgent":true,"paxQty":1,"currCode":"IDR","buying":0.0,"selling":0.0,"disc":0.0,"extraDisc":0.0,"serFee":0.0,"ppn":0.0,"pph23":0.0,"stamp":0.0,"deposit":0.0,"remark1":"contoh buat test invoice dari tiket doang","remark2":null,"taxNo":"123456","ppnCode":null,"pph23Code":null,"src":"G","mark":"A","createdBy":9,"createdDate":"2018-08-20T11:59:27.9091719+07:00","changedBy":9,"changedDate":"2018-08-20T11:59:27.9091719+07:00","odate":null,"referral":"invoice","depos":null}}
		
		$res_json = json_decode($result, true);
		$invoice_code = $res_json['data']['code'];
		$created_date = $res_json['data']['createdDate'];
		$changed_date = $res_json['data']['changedDate'];
		$created_by = $res_json['data']['createdBy'];
		$changed_by = $res_json['data']['changedBy'];
		
		$client->resetParameters();
		$data = array();
		$data['curr'] = 'IDR';
		$data['prodType'] = 'TICKD';
		$data['searchBy'] = 'pnr';
		$data['search'] = $res['booking_code'];
		$client->setUri($host . '/api/ticket-trans/uninv-lists');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'search kode ' . $e->getMessage());
			exit();
		}
		
		$this->logResponse("log/GTASSInvoiceGeneralSearch.html", $response);
		// success [{"id":307,"lineNo":1,"bookCode":"DICOBA","airName":"Lion Air 2","tickNo":"990888","paxName":"MR maulana ganteng","flightDate":"2018-08-20T00:00:00","route":"CGK-BGR","paxPaid":111000.00,"nta":106000.00}]
		
		$res_json = json_decode($result, true);
		$ticket_id = (string) $res_json[0]['id'];
		
		$client->resetParameters();
		$data = array();
		$data['act'] = 'add';
		$data['code'] = $invoice_code;
		$data['dChecked'] = array($ticket_id); // 307
		$data['date'] = date('Y-m-d', $res['issued_date']); // 2018-08-21
		$data['locId'] = 1;
		$data['referral'] = 'invoice';
		$data['tourCode'] = '';
		$data['type'] = 'tick';
		$json = json_encode($data);
		$client->setUri($host . '/api/invoice/updatedetail?act=add');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'include ticket ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'include ticket ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSInvoiceGeneralIncludeTicket.html", $response);
		// success {"count":0,"errorNo":0,"success":true,"message":"Success insert record.","data":null}
		
		$client->resetParameters();
		$data = array();
		$data['code'] = $invoice_code;
		$client->setUri($host . '/api/invoice/detaillist');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'detail invoice ' . $e->getMessage());
			exit();
		}
		
		$this->logResponse("log/GTASSInvoiceGeneralDetail.html", $response);
		// success [{"no":"8e9bbd03-79f7-449b-a486-2f18634a44c4","companyId":1,"id":"307","lineNo":1,"code":"0118000428","invCode":null,"supCode":"A00013","supName":"Lion Air 2","bookCode":"DICOBA","tickNo":"990888","paxName":"MR maulana ganteng","descr":"20 AUG; CGK-BGR","paxPaid":111000.00,"nta":106000.000000,"src":"tick","srcFullName":"1. Ticket","srcOrder":1}]
		
		$client->resetParameters();
		$data = array();
		$client->setUri($host . '/api/invoice/get-price/' . $invoice_code);
		$client->setParameterGet($data);
		
		try {
			$response = $client->request(Zend_Http_Client::GET);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSInvoiceGeneralDetailPrice.html", $response);
		// success {"buying":106000.000000,"selling":111000.00,"disc":0.00,"extraDisc":0.00,"serFee":0.00,"ppn":0.000000,"pph23":0.000000,"gross":111000.00,"netSales":111000.00,"paxPaid":111000.000000}
		
		$client->resetParameters();
		$data = array();
		$data['act'] = 'edit';
		$data['attn'] = '';
		$data['buying'] = $res_json['buying'];
		$data['changedBy'] = $changed_by; // 9
		$data['changedDate'] = $changed_date; // date('c', time()); // 2018-08-20T11:59:27.91
		$data['changedInitial'] = 'OD';
		$data['cnEdCode'] = NULL;
		$data['code'] = $invoice_code;
		$data['companyId'] = 1;
		$data['createdBy'] = $created_by; // 9
		$data['createdDate'] = $created_date; // date('c', time()); // 2018-08-20T11:59:27.91
		$data['currCode'] = 'IDR';
		$data['custCode'] = $customer_data['code']; // COA SUB AGENT VERSA
		$data['custName'] = $customer_data['name']; // COA SUB AGENT VERSA
		$data['custPhone'] = $customer_data['phone']; // COA SUB AGENT VERSA
		$data['date'] = $data['dueDate'] = $data['odate'] = date('Y-m-d', $res['issued_date']); // 2018-08-20
		$data['depos'] = array();
		$data['invCode'] = NULL;
		$data['isCredit'] = false;
		$data['isWG'] = false;
		$data['isDpSubAgent'] = true;
		$data['locName'] = 'Pusat';
		$data['locationId'] = 1;
		$data['mark'] = 'A';
		$data['deposit'] = 0;
		$data['disc'] = $res_json['disc'];
		$data['extraDisc'] = $res_json['extraDisc'];
		$data['gross'] = $res_json['gross'];
		$data['netSales'] = $res_json['netSales'];
		$data['oPpn'] = $res_json['ppn'];
		$data['paxPaid'] = $res_json['paxPaid'];
		$data['selling'] = $res_json['selling'];
		$data['pph23'] = $res_json['pph23'];
		$data['paxQty'] = $res['qty'];
		$data['pph23Code'] = $data['pph23IsInclude'] = $data['pph23Rate'] = $data['pph23Source'] = $data['ppnCode'] = $data['ppnIsInclude'] = $data['ppnRate'] = $data['ppnSource'] = NULL;
		$data['ppn'] = 0;
		$data['prodCode'] = 'TICKD';
		$data['prodCodeSrc'] = 'TICK';
		$data['rate'] = 1;
		$data['referral'] = 'invoice';
		$data['remark1'] = $remark1;
		$data['remark2'] = NULL;
		$data['salesBy'] = 'M0003';
		$data['salesName'] = 'PT';
		$data['serFee'] = 0;
		$data['src'] = 'G';
		$data['stamp'] = 0;
		$data['status'] = 'Active';
		$data['taxNo'] = 0;
		$data['tourCode'] = NULL;
		$json = json_encode($data);
		$client->setUri($host . '/api/invoice/update?act=confirm');
		$client->setRawData($json, 'application/json');
		
		try {
			$response = $client->request('POST');
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		
		$res_json = json_decode($result, true);
		if ($res_json['success'] != true) {
			logRes("log/gtass_error.txt", 'confirm invoice ' . $res_json['message']);
		} else {
			logRes("log/gtass_success.txt", 'confirm invoice ' . $res_json['message']);
		}
		
		$this->logResponse("log/GTASSInvoiceGeneralConfirm.html", $response);
		// success {"count":0,"errorNo":0,"success":true,"message":"Success confirm invoice.","data":null}
		
		return $res_json['success'];
	}
	
	function isAlreadyDepAgent($res)
	{
		$dsareport_list = array();
		$dsareport_list = $this->getDepositAgentCb($res['date'], 'C0100051');
		foreach ($dsareport_list['data'] as $k => $v) {
			if ( ($v['descr'] == $res['descr']) && ($v['amount'] == $res['amount']) ) return true;
		}
		return false;
	}
	
	function isCoa($coa_code)
	{
		$coa_list = array();
		$coa_list = $this->getCOA($coa_code);
		foreach ($coa_list['data'] as $k => $v) {
			if ($v['code'] == $coa_code) return true;
		}
		return false;
	}
	
// GET DATA
	function getUser()
	{
		// USER CODE
		/************************************
		 * kode	* nama			* initial	*
		 ************************************
		 * 10	* AQDA			* AQ		*
		 *		* GSO			*	 		*
		 * 		* GSU			*			*
		 * 7	* hafsyahsuki	* hki		*
		 * 8	* lia			* lia		*
		 * 9	* ODET			* OD		*
		 * 3	* putut			* PT		*
		 * 4	* YOGO BUDIONO	* yg		*
		 * 6	* Yanti Nurmala	* YN		*
		 ************************************
		 */
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$client->setUri($host . '/api/user/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSUserList.html", $response);
	}
	
	function getDepartement()
	{
		// DEPARTEMENT CODE
		/********************************************
		 * kode	* nama					* initial	*
		 ********************************************
		 * 2	* AKUNTING				* ACC		*
		 * 8	* BUSINESS DEVELOPMENT	* BD	 	*
		 * 9	* BUSINESS ANALYST		* BS		*
		 * 1	* CEO					* CEO		*
		 * 4	* FINANCE				* FN		*
		 * 6	* IT SUPPORT			* IT		*
		 * 7	* MEDIA					* MD		*
		 * 5	* MARKETING				* MR		*
		 * 10	* OPERATION				* OP		*
		 * 3	* TRAVEL CONSULTAN		* TC		*		
		 ********************************************
		 */
		 
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$client->setUri($host . '/api/department/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSDepartementList.html", $response);
	}
	
	function getCOA($coa_code)
	{
		// COA CODE
		/********************************************
		 * kode		* description					*
		 ********************************************
		 * 111101	* Kas IDR						*
		 * 111110	* Kas USD						*
		 * 111501	* MANDIRI - 1210068900079  IDR	*
		 * 111502	* BCA - 270-0193881 IDR			*
		 * 111503	* BRI - 033801000680306  IDR	*
		 * 111504	* MAY BANK - 2427001160 IDR		*
		 * 111505	* OCBC - 133800000878 IDR		*
		 * 111506	* BCA - 5440307037  IDR			*
		 * 111507	* MANDIRI - 1210060608886 IDR	*
		 * 111508	* MANDIRI - 1210006595999 IDR	*
		 * 111509 	* MANDIRI - 1220004313048 IDR	*
		 * 111510	* BCA -: 544-0305450  IDR		*
		 * 111511	* MAY BANK - 2427003336  IDR	*
		 * 111512	* BCA - 545-0634567 IDR			*
		 * 111513	* BCA - 545-0240909  IDR		*
		 * 111514	* BCA -  544-0144561  IDR		*
		 * 111515	* BNI 46  - 5506677889  IDR		*
		 * 111516	* PERMATA BANK - 702040140  IDR	*
		 * 111517	* BANK MANDIRI - 1210008819991	*
		 * 111518	* BCA - 2700232088  IDR			*
		 * 111519	* OCBC - 133811232379  IDR		*
		 * 111520	* BNI 46  - 2342122017  IDR		*
		 * 111600	* Kas Perantara - IDR			*
		 ********************************************
		 */
		
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = $coa_code;
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/coa/list');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSCOAList.html", $response);
		
		return $res_json;
	}
	
	function getCustomer()
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = '';
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 50;
		$client->setUri($host . '/api/customer/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		
		$this->logResponse("log/GTASSCustomerList.html", $response);
	}
	
	function getCustomerData($customer_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = $customer_code;
		$data['take'] = 50;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 1000;
		$client->setUri($host . '/api/customer/list');
		$client->setParameterPost($data);
		$response = $client->request(Zend_Http_Client::POST);
		$result = $response->getBody();
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSCustomerData.html", $response);
		
		return $res_json['data'][0];
	}
	
	function getDepositAgentCb($date, $cust_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = date('d M Y', $date);
		$data['take'] = 10;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 1000;
		$data['custCode'] = $cust_code; //C0100051 : Sub Agent Versa
		$client->setUri($host . '/api/depo-sa-cb/list');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSDepositAgentCbList.html", $response);
		
		return $res_json;
	}
	
	function getDsaReport($date, $cust_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();;
		$data['dateFrom'] = date('Y-m-d', $date);
		$data['dateTo'] = date('Y-m-d', $date);
		$data['loc'] = 1;
		$data['custCode'] = $cust_code; // C0100051 : Sub Agent Versa
		$client->setUri($host . '/api/dsa-report/lists');
		$client->setParameterPost($data);
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", $e->getMessage());
			exit();
		}
		$res_json = json_decode($result, true);
		
		$this->logResponse("log/GTASSDSAReportList.html", $response);
		
		return $res_json;
	}
	
	function isAlreadyResTicket($date, $booking_code)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = date('d M Y', $date); // BY DATE 21 Aug 2018
		$data['take'] = 10;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 1000;
		$data['voidStatus'] = false;
		$client->setUri($host . '/api/ticket-trans/list');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'ticket list ' . $e->getMessage());
			exit();
		}
		
		$this->logResponse("log/GTASSReservationTicketList.html", $response);
		
		$res_json = json_decode($result, true);
		foreach ($res_json['data'] as $k => $v) {
			if ($v['bookCode'] == $booking_code) return true;
		}
		
		return false;
	}
	
	function isAlreadyInvoice($date, $remark1)
	{
		$client = &$this->client;
		$host = $this->url;
		
		$client->resetParameters();
		$data = array();
		$data['search'] = date('d M Y', $date); // BY DATE 21 Aug 2018
		$data['take'] = 10;
		$data['skip'] = 0;
		$data['page'] = 1;
		$data['pageSize'] = 1000;
		$data['voidStatus'] = false;
		$client->setUri($host . '/api/invoice/list');
		$client->setParameterPost($data);
		
		try {
			$response = $client->request(Zend_Http_Client::POST);
			$result = $response->getBody();
		} catch (Exception $e) {
			echo $e->getMessage();
			logRes("log/gtass_error.txt", 'invoice list ' . $e->getMessage());
			exit();
		}
		
		$this->logResponse("log/GTASSReservationTicketList.html", $response);
		
		$res_json = json_decode($result, true);
		foreach ($res_json['data'] as $k => $v) {
			if ($v['remark1'] == $remark1) return true;
		}
		
		return false;
	}
}
