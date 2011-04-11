<?php

require_once(TOOLKIT . '/class.author.php');

Class extension_twitter extends Extension
{
    private $_consumerKey = "";
    private $_consumerSecret = "";
    private $_screen_name = "";
    private $_isConnectCallbackFromTwitter = false;
    protected static $fields = array();
    /**
     * @return array
     * @description Get about information
     */
    public function about()
    {
        return array('name' => 'Twitter',
                     'version' => '1.1',
                     'release-date' => '27-01-2011',
                     'author' => array('name' => 'Simon de Turck',
                                       'website' => 'http://zimmen.com',
                                       'email' => 'simon@zimmen.com'),
                     'description' => 'This extension brings twitter to your website!');
    }

    /**
     * @return void
     * @description Uninstall extension
     */
    public function uninstall() {
        $this->_Parent->Configuration->remove('twitter');
        $this->_Parent->saveConfig();
		$this->_Parent->Database->query("DROP TABLE `tbl_fields_autotweet`");
	}

    /**
     * @return bool
     * @description Install extension
     */
	public function install() {
        $this->_Parent->Configuration->set('consumerKey', '6GH0r93skQtyF4hTxtZCw', 'twitter');
        $this->_Parent->Configuration->set('consumerSecret', 'Qs3mTlcv4WdpodS2Hfhx5mnqzpxVZ6dxNXdD7bYVuc', 'twitter');
        $this->_Parent->saveConfig();

		$this->_Parent->Database->query("
			CREATE TABLE IF NOT EXISTS `tbl_fields_autotweet` (
				`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
				`field_id` INT(11) UNSIGNED NOT NULL,
				`expression` VARCHAR(140) DEFAULT NULL,
				`allow_override` ENUM('yes', 'no') DEFAULT 'no',
				`hide` ENUM('yes', 'no') DEFAULT 'no',
				PRIMARY KEY (`id`),
				KEY `field_id` (`field_id`)
			)
		");

		return true;
	}

    /**
     * Delegation
     * @return array
     */
    public function getSubscribedDelegates()
    {
        return array(
            /*array(
                'page' => '/blueprints/events/new/',
                'delegate' => 'AppendEventFilter',
                'callback' => 'addFilterToEventEditor'
            ),

            array(
                'page' => '/blueprints/events/edit/',
                'delegate' => 'AppendEventFilter',
                'callback' => 'addFilterToEventEditor'
            ),

            array(
                'page' => '/blueprints/events/new/',
                'delegate' => 'AppendEventFilterDocumentation',
                'callback' => 'addFilterDocumentationToEvent'
            ),

            array(
                'page' => '/blueprints/events/edit/',
                'delegate' => 'AppendEventFilterDocumentation',
                'callback' => 'addFilterDocumentationToEvent'
            ),
            array(
                'page' => '/frontend/',
                'delegate' => 'EventPreSaveFilter',
                'callback' => 'processEventData'
            ),*/

            array(
                'page' => '/system/preferences/',
                'delegate' => 'AddCustomPreferenceFieldsets',
                'callback' => 'appendPreferences'
            ),
    
            array(
                'page' => '/publish/new/',
                'delegate' => 'EntryPostCreate',
                'callback' => 'compileBackendFields'
            ),

            array(
                'page' => '/publish/edit/',
                'delegate' => 'EntryPostEdit',
                'callback' => 'compileBackendFields'
            ),
            array(
				'page' => '/backend/',
				'delegate' => 'InitaliseAdminPageHead',
				'callback' => 'addToHead'
			)
        );
    }

    public function getConsumerKey()
    {
        return General::Sanitize($this->_Parent->Configuration->get('consumerKey', 'twitter'));
    }

    public function getConsumerSecret()
    {
        return General::Sanitize($this->_Parent->Configuration->get('consumerSecret', 'twitter'));
    }

    public function getScreenName()
    {
        return General::Sanitize($this->_Parent->Configuration->get('screen_name', 'twitter'));
    }

    public function getEntryId(){
        //print_r($this->_Parent);
        //return $this->_Parent->_context['entry_id'];
    }

    /**
     * @param  $entry
     * @return DOMXPath
     * Gets XPATH Dom for entry.
     * Function by Rowan Lewis <me@rowanlewis.com>
     */
    public function getXPath($entry) {
		$entry_xml = new XMLElement('entry');
		$section_id = $entry->_fields['section_id'];
		$data = $entry->getData(); $fields = array();

		$entry_xml->setAttribute('id', $entry->get('id'));

		$associated = $entry->fetchAllAssociatedEntryCounts();

		if (is_array($associated) and !empty($associated)) {
			foreach ($associated as $section => $count) {
				$handle = $this->_Parent->Database->fetchVar('handle', 0, "
					SELECT
						s.handle
					FROM
						`tbl_sections` AS s
					WHERE
						s.id = '{$section}'
					LIMIT 1
				");

				$entry_xml->setAttribute($handle, (string)$count);
			}
		}

		// Add fields:
		foreach ($data as $field_id => $values) {
			if (empty($field_id)) continue;

			$field =& $entry->_Parent->fieldManager->fetch($field_id);
			$field->appendFormattedElement($entry_xml, $values, false, null);
		}

		$xml = new XMLElement('data');
		$xml->appendChild($entry_xml);
		$dom = new DOMDocument();
		$dom->strictErrorChecking = false;
		$dom->loadXML($xml->generate(true));

		$xpath = new DOMXPath($dom);

		if (version_compare(phpversion(), '5.3', '>=')) {
			$xpath->registerPhpFunctions();
		}

		return $xpath;
	}
    
    /*-------------------------------------------------------------------------
		Fields:
	-------------------------------------------------------------------------*/
	public function registerField($field) {
		self::$fields[] = $field;
	}

	public function compileBackendFields($context) {
		foreach (self::$fields as $field) {
			$field->compile($context['entry']);
		}
	}

	public function compileFrontendFields($context) {
		foreach (self::$fields as $field) {
			$field->compile($context['entry']);
		}
	}

    /*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/
    private function __ConnectToTwitter()
    {
        require_once 'lib/twitter.php';
        $twitter = new Twitter($this->_consumerKey, $this->_consumerSecret);
        $twitter->oAuthRequestToken($_SERVER['HTTP_REFERER']);
        if (!$this->_isConnectCallbackFromTwitter) {
            $twitter->oAuthAuthorize();
            return false;
        } else {
            $response = $twitter->oAuthAccessToken($_GET['oauth_token'], $_GET['oauth_verifier']);
            $this->_Parent->Configuration->set('oauth_token', $response['oauth_token'], 'twitter');
            $this->_Parent->Configuration->set('oauth_token_secret', $response['oauth_token_secret'], 'twitter');
            $this->_Parent->Configuration->set('user_id', $response['user_id'], 'twitter');
            $this->_Parent->Configuration->set('screen_name', $response['screen_name'], 'twitter');
            $this->_Parent->saveConfig();
            header('location: ' . $_SERVER['REDIRECT_URL']);
            return true;
        }
    }

    private function __DisconnectFromTwitter()
    {
        $this->_Parent->Configuration->remove('oauth_token', 'twitter');
        $this->_Parent->Configuration->remove('oauth_token_secret', 'twitter');
        $this->_Parent->Configuration->remove('user_id', 'twitter');
        $this->_Parent->Configuration->remove('screen_name', 'twitter');
        unset($this->_screenName);
        $this->_Parent->saveConfig();
    }

    private function __SendTestTweet()
    {
        require_once 'lib/twitter.php';
        $twitter = new Twitter(General::Sanitize($this->_Parent->Configuration->get('consumerKey', 'twitter')), General::Sanitize($this->_Parent->Configuration->get('consumerSecret', 'twitter')));
        $twitter->setOAuthToken(General::Sanitize($this->_Parent->Configuration->get('oauth_token', 'twitter')));
        $twitter->setOAuthTokenSecret(General::Sanitize($this->_Parent->Configuration->get('oauth_token_secret', 'twitter')));
        $response = $twitter->statusesUpdate($_POST['tweet']);
    }

    public function appendPreferences($context)
    {

        $this->_isConnectCallbackFromTwitter = isset($_GET['oauth_token']) ? true : false;
        $this->_consumerKey = General::Sanitize($this->_Parent->Configuration->get('consumerKey', 'twitter'));
        $this->_consumerSecret = General::Sanitize($this->_Parent->Configuration->get('consumerSecret', 'twitter'));
        $this->_screenName = General::Sanitize($this->_Parent->Configuration->get('screen_name', 'twitter'));

        $ckcsAreOk = !empty($this->_consumerKey) && !empty($this->_consumerSecret);

        if ((isset($_POST['action']['twitter_connect']) && $ckcsAreOk) || $this->_isConnectCallbackFromTwitter) {
            $this->__ConnectToTwitter();
        } else if (isset($_POST['action']['twitter_disconnect'])) {
            $this->__DisconnectFromTwitter();
        } else if (isset($_POST['action']['twitter_test'])) {
            $this->__SendTestTweet();
        }

        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', 'Twitter'));

        $div = new XMLElement('div', NULL, array('class' => 'label'));
        $span = new XMLElement('span');

        $connectButton = new XMLElement('button', __('Connect to Twitter'), array('name' => 'action[twitter_connect]', 'type' => 'submit'));
        $disconnectButton = new XMLElement('button', __('Disconnect from Twitter'), array('name' => 'action[twitter_disconnect]', 'type' => 'submit'));

        if ($ckcsAreOk && empty($this->_screenName)) {
            //Not yet connected
            $disconnectButton->setAttribute('disabled', 'true');
        } else if ($ckcsAreOk) {
            //connected
            $connectButton->setAttribute('disabled', 'true');
            $span->appendChild(new XMLElement('h5', __('Connected to ') . "@" . $this->_screenName, array('class' => 'invalid')));
            $span->appendChild(new XMLElement('textarea', "Hello from the #symphonyCMS #twitterextension", array('name' => 'tweet')));
            $span->appendChild(new XMLElement('button', __('Send test tweet'), array('name' => 'action[twitter_test]', 'type' => 'submit')));
        } else {
            //no config
            $span->appendChild(new XMLElement('div', __('Consumer Key and Secret are missing. Please check your configuration. See the readme for more info'), array('class' => 'invalid')));
            $connectButton->setAttribute('disabled', 'true');
            $disconnectButton->setAttribute('disabled', 'true');
        }

        $span->appendChild($connectButton);
        $span->appendChild($disconnectButton);

        $div->appendChild($span);

        $div->appendChild(new XMLElement('p', __('You need to connect this site to the twitter API.
      Click "Connect to Twitter", login to the twitter account you want to use and allow read and write access. You will return on this page afterwards!'), array('class' => 'help')));

        $group->appendChild($div);
        $context['wrapper']->appendChild($group);

    }

    /**
     * @param  $context
     * @return void
     * @description Adds css and script to the head
     */
	public function addToHead($context)
	{
        Administration::instance()->Page->addScriptToHead(URL.'/extensions/twitter/assets/twitter_ext.js', 302, true);
		Administration::instance()->Page->addStylesheetToHead(URL.'/extensions/twitter/assets/twitter_ext.css', 'screen', 304);
	}	
}

?>