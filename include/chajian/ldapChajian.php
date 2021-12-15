<?php
/**
	LDAP插件
*/
class ldapChajian extends Chajian{
	private $ldap_url = '';
    private $ldap_base_dn = '';
    private $ad_domain = '';
    private $ldap_conn;

    protected function initChajian()
	{
		$this->ldap_url = getconfig('ldap_url','');
        $this->ldap_base_dn = getconfig('ldap_base_dn','');
		$this->ad_domain = getconfig('ad_domain','');
		$this->ldap_conn = ldap_connect($this->ldap_url);
		ldap_set_option($this->ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($this->ldap_conn, LDAP_OPT_REFERRALS, 0);
	}

	/**
	 * 取得Active Directory使用者資訊
	 *
	 * @param string $user 使用者AD帳號名稱
	 * @param string $pass 使用者AD帳號密碼
	 *
	 * @return array|string 結果(正常=使用者資訊/異常=錯誤訊息)
	 */
	public function getUserInfo($user, $pass)
	{
		$msg = '';

        if ($this->ldap_url === '') {
			$msg += 'LDAP URL未設定，請檢查';
		}

        if ($this->ldap_base_dn === '') {
			$msg += 'LDAP Base DN未設定，請檢查';
		}

        if($msg)
        {
            return $msg;
        }

		$userid = $this->ad_domain !== '' ? $this->ad_domain . '\\' . $user : $user;
		$passwd = $pass;

		if (!ldap_bind($this->ldap_conn, $userid, $passwd)) {
			return 'LDAP帳號或密碼不符';
		}

		$search_filter = '(&(objectCategory=person)(sAMAccountName=' . $user . '))';

		$attributes = array();
		$attributes[] = 'samaccountname'; //使用者代號
		$attributes[] = 'cn'; //全名
		$attributes[] = 'distinguishedname';
		$attributes[] = 'displayname'; //部門代號(取'顯示名稱'欄位前7碼)
		$attributes[] = 'department'; //部門名稱
		$attributes[] = 'title'; //職位
		$attributes[] = 'manager'; //主管DN
		$attributes[] = 'mail'; //eMail
		$attributes[] = 'telephonenumber'; //桌機號碼
		$attributes[] = 'mobile'; //手機號碼
		$attributes[] = 'wwwhomepage'; //WeChat代號(借用'網頁'欄位)
		$attributes[] = 'useraccountcontrol'; //帳戶選項

		$search_result = ldap_search($this->ldap_conn, $this->ldap_base_dn, $search_filter, $attributes);
		$entries = ldap_get_entries($this->ldap_conn, $search_result);

		$userID = strtoupper(trim($entries[0]['samaccountname'][0])); //工號需轉成全大寫
		$userName = trim($entries[0]['cn'][0]);
		$organizationID = (function ($o) {
			if (strpos($o, 'OU=1.台灣陽程') !== false) {
				return 'USUN-TW';
			} else if (strpos($o, 'OU=2.上海陽程') !== false) {
				return 'USUN-SH';
			} else  if (strpos($o, 'OU=3.佛山陽程') !== false) {
				return 'USUN-FS';
			} else {
				return 'USUN-TW';
			}
		})(trim($entries[0]['distinguishedname'][0])); //組織
		$deptID = substr(trim($entries[0]['displayname'][0]), 0, 7);
		$deptName = trim($entries[0]['department'][0]);
		$userTitle = trim($entries[0]['title'][0]);
		$email = trim($entries[0]['mail'][0]);
		$telephoneNumber = trim($entries[0]['telephonenumber'][0]);
		$cellPhone = trim($entries[0]['mobile'][0]);
		$wechatID = trim($entries[0]['wwwhomepage'][0]);
		$managerID = (function ($ldap_connection, $ldap_base_dn) {
			$search_result = ldap_search($ldap_connection, $ldap_base_dn, '(&(objectCategory=person))', array('samaccountname'));
			if (false !== $search_result) {
				$entries = ldap_get_entries($ldap_connection, $search_result);
				$managerID = strtoupper(trim($entries[0]['samaccountname'][0]));
				return $managerID;
			} else {
				return '';
			}
		})($this->ldap_conn, trim($entries[0]['manager'][0]));
		$isAccountEnabled = !(bool)(intval($entries[0]['useraccountcontrol'][0]) & 0x2);

		//新增或更新
		$userInfo = array("userID"=>$userID,
							"userName"=>$userName,
							"organizationID"=>$organizationID,
							"deptID"=>$deptID,
							"deptName"=>$deptName,
							"userTitle"=>$userTitle,
							"email"=>$email,
							"telephoneNumber"=>$telephoneNumber,
							"cellPhone"=>$cellPhone,
							"wechatID"=>$wechatID,
							"managerID"=>$managerID,
							"isAccountEnabled"=>$isAccountEnabled,
						);

		return $userInfo;
	}
}
?>