<?php
    if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
    require_once(EXTENSIONS . '/twitter/lib/twitter.php');

	class contentExtensionTwitterAutotweet extends AdministrationPage {

        private $entry_id = null;
        private $field_id = null;
        private $status_id = null;
        private $action = null;
        
		public function __construct(&$parent){
			parent::__construct($parent);
		}

		public function __viewIndex()
        {
			$this->entry_id = intval($_GET['entry_id']);
            $this->field_id = intval($_GET['field_id']);
            $this->status_id = $_GET['statusid'];
            $this->action = $_GET['action'];
            
			if(!$this->action) $this->_error("no action");
            $respone=null;
            switch($this->action){
                case "tweet":
                    if(!$this->entry_id || !$this->field_id) $this->_error('no ids');

                    $entryManager = new EntryManager($this);
                    $entry = $entryManager->fetch($this->entry_id);
                    if(!$entry) $this->_error("no data");
                    if(!$entry[0]->_data[$this->field_id]) $this->_error("not tweetable");

                    $response = $this->sendTweet($entry[0]->_data[$this->field_id]['value']);

                    if($response['id_str']){
                        $this->_updateSentField($response);
                    }else{
                        $this->_error("bad response");
                    }
                    break;

                case "delete":
                    if(!$this->status_id) $this->_error('no status id');
                    $response = $this->deleteTweet($this->status_id);
                    if($response['id_str']){
                        $this->_updateDeleteField();
                    }else{
                        $this->_error("bad response");
                    }
                    break;
                default:
                            
            }
            echo json_encode($response);
            exit;
		}

        private function sendTweet($status=NULL){
            if(!$status) return false;
            $twitter = new Twitter(General::Sanitize($this->_Parent->Configuration->get('consumerKey', 'twitter')), General::Sanitize($this->_Parent->Configuration->get('consumerSecret', 'twitter')));
            $twitter->setOAuthToken(General::Sanitize($this->_Parent->Configuration->get('oauth_token', 'twitter')));
            $twitter->setOAuthTokenSecret(General::Sanitize($this->_Parent->Configuration->get('oauth_token_secret', 'twitter')));
            $response = $twitter->statusesUpdate($status);
            return $response;
        }
        private function deleteTweet($status=NULL){
            if(!$status) return false;
            $twitter = new Twitter(General::Sanitize($this->_Parent->Configuration->get('consumerKey', 'twitter')), General::Sanitize($this->_Parent->Configuration->get('consumerSecret', 'twitter')));
            $twitter->setOAuthToken(General::Sanitize($this->_Parent->Configuration->get('oauth_token', 'twitter')));
            $twitter->setOAuthTokenSecret(General::Sanitize($this->_Parent->Configuration->get('oauth_token_secret', 'twitter')));
            $response = $twitter->statusesDestroy($status);
            return $response;
        }
        private function _updateSentField($response){
            $result = $this->_Parent->Database->update(
				array(
					'status_id'			=> $response['id_str'],
					'date_sent'			=> date( 'Y-m-d H:i:s')
				),
				"tbl_entries_data_{$this->field_id}",
				"`entry_id` = '{$this->entry_id}'"
			);
        }
        private function _updateDeleteField(){
            $result = $this->_Parent->Database->update(
				array(
					'status_id'			=> NULL,
					'date_sent'			=> NULL
				),
				"tbl_entries_data_{$this->field_id}",
				"`entry_id` = '{$this->entry_id}'"
			);
        }
        private function _error($msg=NULL){
            echo $msg;
            exit;
        }

	}
 
?>