<?php
!function_exists('adminmsg') && exit('Forbidden');
pwCache::getData(H_P.'data/config.php');
if(!$action){
	if (!$_POST['step']) {
		require_once(R_P.'require/credit.php');
		ifcheck($bbscoin_db['ifopen'],'ifopen');
		ifcheck($bbscoin_db['pay_to_bbscoin'],'pay_to_bbscoin');
		$CreditList = '';
		foreach ($credit->cType as $key => $value) {
			$CreditList	.= "<option value=\"$key\"".($bbscoin_db['pay_credit']==$key ? ' selected' : '').">$value</option>";
		}
		require_once PrintHack('admin');exit;
	} else{
		InitGP(array('config'));
		writeover(H_P."data/config.php","<?php\r\n\$bbscoin_db=".pw_var_export($config).";\r\n?>");//写入配置信息
		adminmsg("operate_success");
	}
} elseif($action == "log"){
	S::gp(array('page'));
	(!is_numeric($page) || $page < 1) && $page = 1;
	$limit = S::sqlLimit(($page-1)*$db_perpage,$db_perpage);
	$rt    = $db->get_one("SELECT COUNT(*) AS sum FROM hack_bbscoin");
	$pages = numofpage($rt['sum'],$page,ceil($rt['sum']/$db_perpage),"$basename&action=log&");
	$query = $db->query("SELECT * FROM hack_bbscoin ORDER BY dateline DESC $limit");
	while($rt = $db->fetch_array($query)){
		$rt['date']  = get_date($rt['dateline']);
		$logdb[] = $rt;
	}
	include PrintHack('admin');exit;
}
?>