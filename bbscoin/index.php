<?php
!function_exists('readover') && exit('Forbidden');
pwCache::getData(H_P.'data/config.php');

!$bbscoin_db['ifopen'] && Showmsg('bbscoin功能暂时关闭');
!$windid && Showmsg('not_login');
if (!$bbscoin_db['bbscoin_wallet_address']) {
    	Showmsg('站长未设置钱包地址');
}
require_once(R_P.'require/credit.php');
InitGP(array('action'));

if (empty($action)) {
	require_once PrintHack('index');footer();
} elseif ($action == 'add') {
	InitGP(array('amount','transaction_hash'));
	if($amount < 1) {
		Showmsg('每次操作涉及的交易积分的不能少于：1');
	}
	$orderid = get_date($timestamp,'YmdHis').num_rand(5);

	if(procLock('pay_bbscoin_'.$winduid)) {
		Showmsg('请勿频繁操作');
	}

	$transaction_info = $db->get_one("SELECT * FROM hack_bbscoin WHERE transaction_hash=".pwEscape($transaction_hash));
	if($transaction_info['transaction_hash']) {
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('交易已处理，请勿重复操作');
	}

	$need_bbscoin = ceil((($amount / $bbscoin_db['pay_ratio']) * 100)) / 100;

	$db->update("INSERT INTO pw_clientorder SET " . pwSqlSingle(array(
		'order_no'	=> $orderid,
		'type'		=> 0,
		'uid'		=> $winduid,
		'paycredit'	=> '',
		'price'		=> $need_bbscoin,
		'number'	=> $amount,
		'date'		=> $timestamp,
		'state'		=> 0
	)));
	$req_data = array(
			"params" => array(
			"transactionHash" => $transaction_hash
		),
		"jsonrpc" => "2.0",
		"method" => "getTransaction"
	);
	$result = getUrlContent($bbscoin_db['bbscoin_walletd'], json_encode($req_data)); 
	$rsp_data = json_decode($result, true);
	$trans_amount = 0;
	if ($rsp_data['result']['transaction']['transfers']) {
		foreach ($rsp_data['result']['transaction']['transfers'] as $transfer_item) {
			if ($transfer_item['address'] == $bbscoin_db['bbscoin_wallet_address']) {
				$trans_amount += $transfer_item['amount'];
			}
		}
	}

	$trans_amount = $trans_amount / 100000000;

	if ($trans_amount == $need_bbscoin) {
		$db->update("UPDATE pw_clientorder SET state='2' WHERE order_no=" . pwEscape($orderid));
		$db->update("INSERT INTO hack_bbscoin SET " . pwSqlSingle(array(
			'orderid'	=> $orderid,
			'transaction_hash'		=> $transaction_hash,
			'address'		=> '',
			'dateline'	=> $timestamp
		)));
		$credit->set($winduid,$bbscoin_db['pay_credit'],$amount);
		require_once(R_P.'require/msg.php');
		$message = array(
			'toUser'	=> $windid,
			'subject'	=> '充值操作成功',
			'content'	=> '充值操作成功，订单号：'.$orderid,
			'other'		=> array(
				'currency'	=> $bbscoin_db['pay_credit'],
				'cname'		=> $credit->cType[$bbscoin_db['pay_credit']],
				'number'	=> $amount
			)
		);
		pwSendMsg($message);
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('充值操作成功');
	} else {
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('充值金额错误');
	}
} elseif ($action == 'pay_to_bbscoin') {
	InitGP(array('amount','walletaddress'));
	$need_point = ceil((($amount / $bbscoin_db['pay_to_coin_ratio']) * 100)) / 100;

	if ($need_point < 1) {
		Showmsg('每次操作涉及的交易积分的不能少于：1');
	}

	if ($bbscoin_db['bbscoin_wallet_address'] == $walletaddress) {
		Showmsg('提现操作失败');
	}

	$real_price = $amount * 100000000 - 1000000;

	if ($real_price <= 0) {
		Showmsg('扣除手续费后提现BBSCoin小于0，无法继续');
	}

	if(procLock('pay_bbscoin_'.$winduid)) {
		Showmsg('请勿频繁操作');
	}

	if ($need_point > $credit->get($winduid,$bbscoin_db['pay_credit'])) {
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('积分不足，不能提现');
	}

	$orderid = get_date($timestamp,'YmdHis').num_rand(5);

	$db->update("INSERT INTO pw_clientorder SET " . pwSqlSingle(array(
		'order_no'	=> $orderid,
		'type'		=> 0,
		'uid'		=> $winduid,
		'paycredit'	=> '',
		'price'		=> $need_bbscoin,
		'number'	=> $amount,
		'date'		=> $timestamp,
		'state'		=> 0
	)));

	$req_data = array(
		'params' => array(
			'anonymity' => 0,
			'fee' => 1000000,
			'unlockTime' => 0,
			'changeAddress' => $bbscoin_db['bbscoin_wallet_address'],
			"transfers" => array(
				0 => array(
				'amount' => $real_price,
				'address' => $walletaddress,
				)
			)
		),
		"jsonrpc" => "2.0",
		"method" => "sendTransaction"
	);

	$result = getUrlContent($bbscoin_db['bbscoin_walletd'], json_encode($req_data)); 
	$rsp_data = json_decode($result, true);

	$trans_amount = 0;
	if ($rsp_data['result']['transactionHash']) {
		$db->update("UPDATE pw_clientorder SET state='2' WHERE order_no=" . pwEscape($orderid));
		$db->update("INSERT INTO hack_bbscoin SET " . pwSqlSingle(array(
			'orderid'	=> $orderid,
			'transaction_hash'		=> $rsp_data['result']['transactionHash'],
			'address'		=> $walletaddress,
			'dateline'	=> $timestamp
		)));
		$credit->set($winduid,$bbscoin_db['pay_credit'],-$need_point);
		require_once(R_P.'require/msg.php');
		$message = array(
			'toUser'	=> $windid,
			'subject'	=> '提现操作成功',
			'content'	=> '提现操作成功，订单号和交易号：'.$orderid.', '.$rsp_data['result']['transactionHash'],
			'other'		=> array(
				'currency'	=> $bbscoin_db['pay_credit'],
				'cname'		=> $credit->cType[$bbscoin_db['pay_credit']],
				'number'	=> $need_point
			)
		);
		pwSendMsg($message);
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('提现操作成功');
	} else {
		procUnLock('pay_bbscoin_'.$winduid);
		Showmsg('提现操作失败');
	}

}
function getUrlContent($url, $data_string) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, 'BBSCoin');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	$data = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $data;
}
?>