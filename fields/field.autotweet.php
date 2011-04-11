<?php

if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

class FieldAutotweet extends Field
{
    protected $_driver = null;
    protected static $ready = true;

    /*-------------------------------------------------------------------------
         Definition:
     -------------------------------------------------------------------------*/

    public function __construct(&$parent)
    {
        parent::__construct($parent);

        $this->_name = 'Auto Tweet';
        $this->_driver = $this->_engine->ExtensionManager->create('twitter');

        // Set defaults:
        $this->set('show_column', 'yes');
        $this->set('allow_override', 'no');
        $this->set('hide', 'no');
    }

    public function createTable()
    {
        $field_id = $this->get('id');

        return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` VARCHAR(255) DEFAULT NULL,
					`value` TEXT DEFAULT NULL,
					`status_id` VARCHAR(255) DEFAULT NULL,
					`date_sent` datetime DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					FULLTEXT KEY `value` (`value`)
				)
			");
    }

    public function allowDatasourceOutputGrouping()
    {
        return false;
    }

    public function allowDatasourceParamOutput()
    {
        return false;
    }

    public function canFilter()
    {
        return false;
    }

    public function canPrePopulate()
    {
        return false;
    }

    public function isSortable()
    {
        return false;
    }

    /**
     * @param  $wrapper
     * @param null $errors
     * @return void
     * Get the field's section settings
     */
    public function displaySettingsPanel(&$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        $order = $this->get('sortorder');

        /*Expression*/
        $div = new XMLElement('div');
        $label = Widget::Label('Message Expression');
        $label->appendChild(Widget::Textarea(
                                "fields[{$order}][expression]",
                                4,
                                10,
                                $this->get('expression')
                            ));

        $help = new XMLElement('p');
        $help->setAttribute('class', 'help');
        $help->setValue('
				To access the other fields, use XPath: <code>{entry/field-one}
				static text {entry/field-two}</code>.
			');

        $div->appendChild($label);
        $div->appendChild($help);
        $wrapper->appendChild($div);

        /*Allow Override*/
        $label = Widget::Label();
        $input = Widget::Input("fields[{$order}][allow_override]", 'yes', 'checkbox');
        if ($this->get('allow_override') == 'yes') {
            $input->setAttribute('checked', 'checked');
        }
        $label->setValue($input->generate() . ' Allow value to be manually overridden');
        $wrapper->appendChild($label);

        /*Hide input*/
        $label = Widget::Label();
        $input = Widget::Input("fields[{$order}][hide]", 'yes', 'checkbox');
        if ($this->get('hide') == 'yes') {
            $input->setAttribute('checked', 'checked');
        }
        $label->setValue($input->generate() . ' Hide this field on publish page');
        $wrapper->appendChild($label);

        /*Show column*/
        $this->appendShowColumnCheckbox($wrapper);
    }

    /**
     * @return bool
     * Save the field's section settings
     */
    public function commit()
    {
        if (!parent::commit()) return false;

        $id = $this->get('id');
        $handle = $this->handle();

        if ($id === false) return false;

        $fields = array(
            'field_id' => $id,
            'expression' => $this->get('expression'),
            'allow_override' => $this->get('allow_override'),
            'hide' => $this->get('hide')
        );

        $this->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");

        return $this->Database->insert($fields, "tbl_fields_{$handle}");
    }

    /**
     * @param  $wrapper
     * @param null $data
     * @param null $flagWithError
     * @param null $prefix
     * @param null $postfix
     * @param null $entry_id
     * @return void
     * Display the enry field
     */
    public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null, $entry_id = null)
    {
        $sortorder = $this->get('sortorder');
        $element_name = $this->get('element_name');
        $allow_override = null;

        if ($this->get('allow_override') != 'yes') {
            $allow_override = array(
                'disabled' => 'disabled'
            );
        }


        if ($this->get('hide') != 'yes') {/*when not hidden*/
            $label = Widget::Label($this->get('label'));


            if (!empty($data['value'])) {/*when there is data*/
                
                $label->appendChild(
                    Widget::Textarea(
                        "fields{$prefix}[$element_name][value]{$postfix}",
                        4,
                        10,
                        @$data['value'],
                        $allow_override
                    )
                );


                if (!empty($data['status_id'])) {/*when it has been tweeted before*/
                    $date = new XMLElement("strong");
                    $date->setValue(__('Tweeted on:') . " " . $data['date_sent']);
                    $button = new XMLElement("a");
                    $button->setValue(__('Delete tweet'));
                    $f_id = $this->get('id');
                    $button->setAttributeArray(
                        array(
                             "href" => "#",
                             "act" => "delete",
                             "s_id" => $data['status_id'],
                             "e_id" => $entry_id,
                             "f_id" => $f_id,
                             "class" => "twitter_ext_send"
                        )
                    );
                    $label->appendChild($date);
                    $label->appendChild($button);
                    $hidden = Widget::Input(
                        "fields{$prefix}[$element_name][status_id]{$postfix}",
                        $data['status_id'],
                        "hidden",
                        NULL
                    );
                    $label->appendChild($hidden);
                    $hidden = Widget::Input(
                        "fields{$prefix}[$element_name][date_sent]{$postfix}",
                        $data['date_sent'],
                        "hidden",
                        NULL
                    );
                    $label->appendChild($hidden);
                } else {/*when it has not yet been tweeted*/
                    $button = new XMLElement("a");
                    $button->setValue(__('Send tweet'));
                    $f_id = $this->get('id');
                    $button->setAttributeArray(
                        array(
                             "href" => "#",
                             "act" => "tweet",
                             "e_id" => $entry_id,
                             "f_id" => $f_id,
                             "class" => "twitter_ext_send"
                        )
                    );
                    $label->appendChild($button);
                }
            }else{/*there is no data yet, seems like a save is neccecarry*/
                $info=new XMLElement("span");
                $info->setValue(__("This entry needs to be (re)saved first"));
                $label->appendChild($info);
                
            }
            $wrapper->appendChild($label);
        }
    }

    /*Input:*/
    
    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $this->_driver->registerField($this);

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;
        if(!is_array($data)){
            return array(
                'handle' => NULL,
                'value' => NULL,
                'status_id'=>NULL,
                'date_sent'=>NULL
            );
        }else{
            return $data;
        }
    }

    /*Output:*/
    
    public function appendFormattedElement(&$wrapper, $data, $encode = false)
    {
        if (!self::$ready) return;

        $element = new XMLElement($this->get('element_name'));
        $element->setAttribute('handle', $data['handle']);
        $element->setValue($data['value']);

        $wrapper->appendChild($element);
    }

    /**
     * @param  $data
     * @param null|XMLElement $link
     * @return XMLElement
     * Get the publishlist view
     */
    public function prepareTableValue($data, XMLElement $link = null, $entry_id=null)
    {
        if (empty($data)) return;
    
       // print_r($entry);
        $allow_override = null;
        
        if ($this->get('allow_override') != 'yes') {
           $allow_override = array(
               'disabled' => 'disabled'
           );
        }
        
        $twitter = new XMLElement('div');
        $twitter->setAttribute('class', 'twitter_ext');

        if (!empty($data['status_id'])) {
            $button = new XMLElement('a');
            $button->setValue(__('Tweet') . " (sent on " . $data['date_sent'] . ")");
            $button->setAttributeArray(
                array(
                    "href" => "#",
                    "class" => "twitter_ext_button"
                )
            );
        }else{
            $button = new XMLElement('a');
                $button->setValue(__('Tweet') . " (" . __("not sent") . ")");
            $button->setAttributeArray(
                array(
                    "href" => "#",
                    "class" => "twitter_ext_button"
                )
            );
        }

        $twitter->appendChild($button);

        $tweetbox = new XMLElement('div');
        $tweetbox->setAttribute('class', 'twitter_ext_tweetbox');

        $tweetbox->appendChild(
            Widget::Textarea("tweet", 4, 10, $data['value'], $allow_override)
        );
        $f_id = $this->get('id');
        //print_r($this->_engine);
       
        if (!empty($data['status_id'])) {
            $button = new XMLElement('a');
            $button->setValue('Delete');
            $button->setAttributeArray(
                array(
                     "href" => "#",
                     "act" => "delete",
                     "s_id" => $data['status_id'],
                     "e_id" => $entry_id,
                     "f_id" => $f_id,
                     "class" => "twitter_ext_send"
                )
            );
        }else{
            $button = new XMLElement('a');
            $button->setValue('Send');
            $button->setAttributeArray(
                array(
                     "href" => "#",
                     "act" => "tweet",
                     "e_id" => $entry_id,
                     "f_id" => $f_id,
                     "class" => "twitter_ext_send"
                )
            );
        }

        $tweetbox->appendChild($button);

        $button = new XMLElement('a');
        $button->setValue('Cancel');
        $button->setAttributeArray(
            array(
                 "href" => "#",
                 "class" => "twitter_ext_cancel"
            )
        );
        $tweetbox->appendChild($button);
        $twitter->appendChild($tweetbox);
        return $twitter;
    }

    /**
     * @param  $entry
     * @return void
     * Creates the tweet based on the expression.
     * Function by Rowan Lewis <me@rowanlewis.com>
     */
    public function compile($entry)
    {
        self::$ready = false;

        $xpath = $this->_driver->getXPath($entry);

        self::$ready = true;

        $entry_id = $entry->get('id');
        $field_id = $this->get('id');
        $expression = $this->get('expression');
        $replacements = array();

        // Find queries:
        preg_match_all('/\{[^\}]+\}/', $expression, $matches);

        // Find replacements:
        foreach ($matches[0] as $match) {
            $result = @$xpath->evaluate('string(' . trim($match, '{}') . ')');

            if (!is_null($result)) {
                $replacements[$match] = trim($result);
            }

            else {
                $replacements[$match] = '';
            }
        }

        // Apply replacements:
        $value = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $expression
        );

        // Save:
        $result = $this->Database->update(
            array(
                 'handle' => Lang::createHandle($value),
                 'value' => $value
            ),
            "tbl_entries_data_{$field_id}",
            "`entry_id` = '{$entry_id}'"
        );
    }
}

?>