<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

use \Longman\TelegramBot\Request;
use \Longman\TelegramBot\Telegram;
use \Monolog\Handler\FilterHandler;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;


class PluginNiceurl_ModuleNiceurl extends Module {
	protected $oMapper;
	protected $oUserCurrent=null;
	
	public function Init() {		
		$this->oMapper=Engine::GetMapper(__CLASS__);
		$this->oUserCurrent=$this->User_GetUserCurrent();
	}
	
	
	/**
	 * Получает топик по его латинсокму названию
	 *	
	 * @param string $sTitle
	 * @return ModuleTopic_EntityTopic
	 */
	public function GetTopicByTitleLat($sTitle) {
		return $this->Topic_GetTopicById($this->oMapper->GetTopicByTitleLat($sTitle));
	}
	/**
	 * Обновление доп. информации о топике
	 *
	 * @param PluginNiceurl_ModuleNiceurl_EntityTopic $oWpTopic
	 * @return unknown
	 */
	public function UpdateTopic(PluginNiceurl_ModuleNiceurl_EntityTopic $oNiceurlTopic) {
		//$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('niceurl_topic_update'));
		return $this->oMapper->UpdateTopic($oNiceurlTopic);
	}
	/**
	 * Удаляет доп. инфу о топике
	 *
	 * @param unknown_type $sId
	 * @return unknown
	 */
	public function DeleteTopicById($sId) {
		//$this->Cache_Clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,array('niceurl_topic_update'));
		return $this->oMapper->DeleteTopicById($sId);
	}
	/**
	 * Обновление URL топика
	 *
	 * @param unknown_type $oTopic
	 */
	public function UpdateTopicUrl($oTopic) {
	    $oNiceurlTopic=Engine::GetEntity('PluginNiceurl_ModuleNiceurl_EntityTopic');
    	$oNiceurlTopic->setId($oTopic->getId());
    	    	
    	$i=2;
    	$sTitle=$sTitleSold=func_translit($oTopic->getTitle());    	
    	while (($oNiceurlTopicOld=$this->PluginNiceurl_Niceurl_GetTopicByTitleLat($sTitle)) and $oNiceurlTopicOld->getId()!=$oNiceurlTopic->getId()) {
    		$sTitle=$sTitleSold.'_'.$i;
    		$i++;
    	}
    	$oNiceurlTopic->setTitleLat($sTitle);
    	$oTopic->setTitleLat($sTitle);

        // Post message to Telegram
        // =======================================================================================
        // $this->shareToTelegram($oTopic, $sTitle);
        // ====================================================================================
        // End posting message to Telegram

        $this->PluginNiceurl_Niceurl_UpdateTopic($oNiceurlTopic);
	}
	/**
	 * Получает список доп. данных топика по массиву ID
	 *
	 * @param unknown_type $aTopicId
	 * @return unknown
	 */
	public function GetTopicsByArrayId($aTopicId) {
		if (!is_array($aTopicId)) {
			$aTopicId=array($aTopicId);
		}
		$aTopicId=array_unique($aTopicId);	
		
		/*
		 *  Файловый кеш приводил к жутким тормозам, особенно удаление по тегу
		 * 
		$aTopics=array();	
		$s=join(',',$aTopicId);
		if (false === ($data = $this->Cache_Get("niceurl_topic_id_{$s}"))) {			
			$data = $this->oMapper->GetTopicsByArrayId($aTopicId);
			foreach ($data as $oTopic) {
				$aTopics[$oTopic->getId()]=$oTopic;
			}
			$this->Cache_Set($aTopics, "niceurl_topic_id_{$s}", array("niceurl_topic_update"), 60*60*24*1);
			return $aTopics;
		}	
		*/
		
		return $this->oMapper->GetTopicsByArrayId($aTopicId);
	}
	
	
	public function GetTopicsHeadAll($iCurrPage,$iPerPage) {
		return $this->oMapper->GetTopicsHeadAll($iCurrPage,$iPerPage);
	}
	
	public function BuildUrlForTopic($oTopic) {		
		$sUrlSource=Config::Get('plugin.niceurl.url').Config::Get('plugin.niceurl.url_postfix');
		
		$aPreg=array(
			'%year%' => date("Y",strtotime($oTopic->GetDateAdd())),
			'%month%' => date("m",strtotime($oTopic->GetDateAdd())),
			'%day%' => date("d",strtotime($oTopic->GetDateAdd())),
			'%hour%' => date("H",strtotime($oTopic->GetDateAdd())),
			'%minute%' => date("i",strtotime($oTopic->GetDateAdd())),
			'%second%' => date("s",strtotime($oTopic->GetDateAdd())),
			//'%login%' => $oTopic->GetUser()->getLogin(),
			//'%blog%' => $oTopic->GetBlog()->getUrl(),
			'%id%' => $oTopic->GetId(),
			'%title%' => $oTopic->GetTitleLat(),
		);
		
		$sBlogUrl=$oTopic->GetBlog()->getUrl();
		if ($oTopic->GetBlog()->getType()=='personal') {
			$sBlogUrl=Config::Get('plugin.niceurl.url_personal_blog');
			$sUrlSource=str_replace('%blog%',Config::Get('plugin.niceurl.url_personal_blog'),$sUrlSource);
		}
		$aPreg['%blog%']=$sBlogUrl;
		
		if (strpos($sUrlSource,'%login%')!==false) {
			if (!($oUser=$oTopic->GetUser())) {
				$oUser=$this->User_GetUserById($oTopic->getUserId());
			}
			$aPreg['%login%']=$oUser->getLogin();
		}

		$sUrl=strtr($sUrlSource,$aPreg);

		return Config::Get('path.root.web').$sUrl;
	}

    /**
     * @by Karel Wintersky
     *
     * @param $oTopic - объект топика
     * @param $sTitle - латинский URL
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function shareToTelegram($oTopic, $sTitle)
    {
        $sUrlSource = Config::Get('plugin.niceurl.url') . Config::Get('plugin.niceurl.url_postfix');
        $id_topic = $oTopic->getId();
        $id_blog = $oTopic->getBlogId();

        // connect DB
        $dbname = Config::get('db.params.dbname');
        $dbuser = Config::get('db.params.user');
        $dbpass = Config::get('db.params.pass');
        $dbprefix = Config::get('db.table.prefix');
        $dsl = "mysql:host=localhost;port=3306;dbname={$dbname}";
        $dbh = new \PDO($dsl, $dbuser, $dbpass);
        $dbh->exec("SET NAMES UTF-8;");
        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $dbh->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // получим тип блога
        $sql = "SELECT blog_type, blog_url FROM {$dbprefix}blog WHERE blog_id = :blog_id";
        $sth = $dbh->prepare($sql);
        $sth->execute([
            'blog_id' => $id_blog
        ]);
        $blog_info = $sth->fetch();
        $sBlogUrl = empty($blog_info['blog_url']) ? '' : $blog_info['blog_url'];

        // массив подстановки данных
        $aPreg = array(
            '%id%' => $id_topic,
            '%title%' => $sTitle,
        );

        if ($blog_info['blog_type'] == 'personal') {
            $sBlogUrl = Config::Get('plugin.niceurl.url_personal_blog');
            $sUrlSource = str_replace('%blog%', Config::Get('plugin.niceurl.url_personal_blog'), $sUrlSource);
        }

        $aPreg['%blog%'] = $sBlogUrl;
        $sUrl = strtr($sUrlSource, $aPreg);

        $message = Config::Get('path.root.web') . $sUrl;

        // инициализируем монолог
        $log_name = str_replace('$', $_SERVER['DOCUMENT_ROOT'], Config::Get('monolog.logfile'));
        $_logger = new Logger(Config::Get('monolog.channel'));
        $_logger->pushHandler(new StreamHandler($log_name, Logger::DEBUG));

        // получим данные для репоста в телеграм
        $api_key  = Config::Get('telegram.api_key');
        $bot_name = Config::Get('telegram.bot_name');
        $chat_id  = Config::Get('telegram.chat_id');

        $telegram = new Telegram($api_key, $bot_name);
        $data = [
            'chat_id' => $chat_id,
            'text'    => $message,
        ];
        $result = Request::sendMessage($data);

        if ($result->isOk()) {
            $_logger->notice("Message sent");
        } else {
            $_logger->error("Error sending");
            echo 'Sorry message not sent to: ' . $chat_id;
            echo '<pre>';
            var_dump($result);
            die;
        }
    }
}
?>