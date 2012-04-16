<?php
	class MailchimpForm extends Page {
		static $db = array(
			'McApiKey' => 'Varchar(100)',
			'McListId' => 'Varchar(25)',
			'FeedbackErr' => 'Varchar(200)',
			'FeedbackOkay' => 'Varchar(200)',
			'FeedbackDuplicate' => 'Varchar(200)',
			'RequiredFields' => 'Varchar(200)',
			'ActionButton' => 'Varchar(200)'
		);

		static $has_one = array(
		);

		static $allowed_children = 'none';
		public static $icon = "Mailchimp-signup/images/newsletter";

		static $defaults = array(
			'Content' => '$MailchimpForm',
			'FeedbackErr' => 'Er is iets misgegaan met het aanmelden van uw e-mail adres. Probeer het nogmaals.',
			'FeedbackOkay' => "Het aanmelden is gelukt. Binnen enkele minuten ontvangt u een e-mail op ##EMAIL##, met daarin een link om de aanmelding te bevestigen.",
			'FeedbackDuplicate' => 'Dit e-mail adres is al aangemeld voor de nieuwsbrief.',
			'RequiredFields' => 'De velden met een * zijn verplicht.',
			'ActionButton' => 'Aanmelden'
		);

		public function getCMSFields() {
			$oFields = parent::getCMSFields();
			
			$oFields->findOrMakeTab('Root.Content.MCFeedback', _t('MailchimpForm.MCFeedback', 'Mailchi,p Form'));
			
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('McApiKey', _t('MailchimpForm.APIKEY', 'API key MailChimp')));
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('McListId', _t('MailchimpForm.LISTID', 'List ID')));
			
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('FeedbackOkay', _t('MailchimpForm.FEEDBACKOKAY', 'Feedback gelukt')));
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('FeedbackErr', _t('MailchimpForm.FEEDBACKERR', 'MCFeedback fout')));
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('FeedbackDuplicate', _t('MailchimpForm.FEEDBACKDUPLICATE', 'Feedback bestaand mailadres')));
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('RequiredFields', _t('MailchimpForm.REQUIREDFIELDS', 'Indicatie verplichte velden')));
			$oFields->addFieldToTab('Root.Content.MCFeedback', new TextField('ActionButton', _t('MailchimpForm.SUBMITBUTTON', 'Tekst op verzendknop')));

			return $oFields;
		}

	}


	class MailchimpForm_Controller extends Page_Controller {
		public static $allowed_actions = array(
			'SubscribeForm', 
			'Subscribe'
		);

		/**
		 * Using $SubscribeForm in the Content area of the page shows
		 * where the form should be rendered into. If it does not exist
		 * then default back to $Form
		 *
		 * @return Array
		 */
		public function index() {
			if ($this->Content && $form = $this->SubscribeForm()) {
				$hasLocation = stristr($this->Content, '$MailchimpForm');

				if($hasLocation) {
					$content = str_ireplace('<p>$MailchimpForm</p>', $form->forTemplate(), $this->Content);
					$content = str_ireplace('$MailchimpForm', $form->forTemplate(), $content);

					return array(
						'Content' => DBField::create('HTMLText', $content),
						'Form' => ""
					);
				}
			}

			return array(
				'Content' => DBField::create('HTMLText', $this->Content),
				'Form' => $form
			);
		}

		public function SubscribeForm() {
			
			if (!($this->McApiKey)) { debug::show('Please, set API key first in CMS'); return false; }
			if (!($this->McListId)) { debug::show('Please, set list id first in CMS'); return false; }
			
			//initialize
			if (!(class_exists('MCAPI'))) {
				require_once 'MCAPI.class.php';
			}
			$oMCAPI = new MCAPI($this->McApiKey);
			$oFields = new FieldSet();
			$oValidator = new RequiredFields();

			// Get list data
			$aListInfo = $oMCAPI->listMergeVars($this->McListId);
			$aGroupInfo = $oMCAPI->listInterestGroupings($this->McListId);
			
			if (!$aListInfo) { debug::show('No signup form found'); return false; }
			foreach($aListInfo as $field) {
				if ($field['public'] && $field['show']) {
					
					//add required * to required fields
					if ($field['req']) { $field['name'] .=' *'; }
					
					switch ($field['field_type']) {
					
						case 'text':
							$oFields->push( $newField = new TextField($field['tag'], $field['name'], $field['default'], $field['size']));
						break;
						
						case 'e-mail':
						case 'email':
							$oFields->push( $newField = new EmailField($field['tag'], $field['name'], $field['default'], $field['size']));
						break;
						
						case 'dropdown':
							
							//set value and key to value
							$optionSet = array();
							foreach($field['choices'] as $opt) { $optionSet[$opt] = $opt; }
							
							$oFields->push( $newField = new DropdownField($field['tag'], $field['name'], $optionSet) );
						break;
						
						case 'radio':
						
							//set value and key to value
							$optionSet = array();
							foreach($field['choices'] as $opt) { $optionSet[$opt] = $opt; }
							
							$oFields->push( $newField = new OptionsetField($field['tag'], $field['name'], $optionSet) );
							break;

						
						default:
							$oFields->push( $newField = new LiteralField($field['tag'], '<br />ERROR: UNSUPPORTED FIELDTYPE ('.$field['field_type'].') -> ' . $field['tag'] .' '. $field['name']));
					}
					
					//add field to list of required fields
					if ($field['req']) { $oValidator->addRequiredField($field['tag']); }
					
					//add description to field
					if (isset($newField) && $field['helptext']) {$newField->setRightTitle($field['helptext']);}
				}
			}
			
			
			foreach($aGroupInfo as $group) {
				
				$groupOptions = array();
				foreach($group['groups'] as $groupValue) {
					$groupOptions[str_ireplace(',', '\,', $groupValue['name'])] = $groupValue['name'];
				}
				
				switch ($group['form_field']) {
					case 'radio':
						$oFields->push( new OptionsetField('groupings_'.$group['id'], $group['name'], $groupOptions) );
					break;
					
					case 'checkboxes':
						$oFields->push( new CheckboxSetField('groupings_'.$group['id'], $group['name'], $groupOptions) );
					break;
					
					case 'dropdown':
						$oFields->push( new DropdownField('groupings_'.$group['id'], $group['name'], $groupOptions) );
					break;
					
					case 'hidden':
						//skip it
					break;
					
					default:
						$oFields->push( new LiteralField($group['id'], '<br />ERROR: UNSUPPORTED GROUP TYPE ('.$group['form_field'].') -> ' . $group['id'] .' '. $group['name']));
				}
			}
			
			
			$oActions = new FieldSet(
				new FormAction('Subscribe', $this->ActionButton), 
				new LiteralField('RequiredNote', '<small class="message">'.$this->RequiredFields.'</small>')
			);

			$oForm = new Form($this, 'SubscribeForm', $oFields, $oActions, $oValidator);

			// Retrieve potentially saved data and populate form fields if values were present in session variables
			$aData = Session::get("FormInfo.".$oForm->FormName().".data");
			if(is_array($aData)) {
				$oForm->loadDataFrom($aData);
			}

			return $oForm;
		}


		public function Subscribe($aData, $oForm) {
			// Load the MCAPI class
			if (!(class_exists('MCAPI'))) {
				require_once 'MCAPI.class.php';
			}
			$oMCAPI = new MCAPI($this->McApiKey);

			// Check whether this e-mail is already registered
			$aMemberInfo = $oMCAPI->listMemberInfo($this->McListId, $aData['EMAIL']);
			$iStatusCode = $oMCAPI->errorCode;

			// Make sure the feedback variables exist just in case of a freak error
			$sFeedbackMsg = "";
			$sFeedbackType = "";

			// Only attempt to subscribe the user if the e-mail address is not found in the list
			if ($oMCAPI->errorCode) {
				$bRetryData = true;
				$sFeedbackType = "bad";
				$sFeedbackMsg = $this->FeedbackErr . 	"<br />Fout melding ".$oMCAPI->errorCode.": " . $oMCAPI->errorMessage;
			} else {
				if (isset($aMemberInfo['data']['status'])) {
					if ($aMemberInfo['data']['status'] == "subscribed") {
						// The e-mail address has already subscribed, provide feedback
						$sFeedbackType = "warning";
						$sFeedbackMsg = $this->FeedbackDuplicate;
						$bDoSubscribe = false;
					} else {
						$bDoSubscribe = true;
					}
				} else {
					$bDoSubscribe = true;
				}

				//passed checks above (no errror, no existing user)
				if ($bDoSubscribe) {
					$aMergeVars = array();
					
					//get list data
					$aListInfo = $oMCAPI->listMergeVars($this->McListId);
					$aGroupInfo = $oMCAPI->listInterestGroupings($this->McListId);

					//loop through input types and add them to merge arrray
					foreach($aListInfo as $field) {
						if ($field['public'] && $field['show']) {
							//add value from field
							if (isset($aData[$field['tag']])) { $aMergeVars[$field['tag']] = $aData[$field['tag']]; }
						}
					}
					
					// same for groups
					$aGroups = array();
					foreach($aGroupInfo as $group) {
						if ($group['form_field'] != 'hidden') {
						
							if (isset($aData['groupings_'.$group['id']])) { 
								//add checkbox groups
								if (is_array($aData['groupings_'.$group['id']])) {
									$aGroups[] = array(
										'id' => $group['id'],
										'groups' => implode(",", $aData['groupings_'.$group['id']])
									);
								} else {
								//add regular groups
									$aGroups[] = array(
										'id' => $group['id'],
										'groups' => $aData['groupings_'.$group['id']]
									);
								}
							}
						}
					}
					$aMergeVars['GROUPINGS'] = $aGroups;	//add groups to mergevars
					

					//actual subscribe
					$iStatusCode = $oMCAPI->listSubscribe($this->McListId, $aData['EMAIL'], $aMergeVars);
					debug::show($oMCAPI->errorMessage);
					if ($oMCAPI->errorCode) {
						$bRetryData = true;
						$sFeedbackType = "error";
						$sFeedbackMsg = $this->FeedbackErr . 	"<br />Fout melding ".$oMCAPI->errorCode.": " . $oMCAPI->errorMessage;
					} else {
						$bRetryData = false;
						$sFeedbackType = "good";
						$sFeedbackMsg = str_ireplace('##EMAIL##', $aData['EMAIL'], $this->FeedbackOkay);
					}
				}
			}

			if ($bRetryData) {
				// Store the submitted data in case the user needs to try again
				Session::set("FormInfo.".$oForm->FormName().".data", $aData);
			} else {
				$aSessData = Session::get("FormInfo.".$oForm->FormName().".data");
				if(is_array($aSessData)) {
					Session::clear("FormInfo.".$oForm->FormName().".data");
				}
			}

			/*
			debug::show($aData);
			debug::show($aMergeVars);
			debug::show( $sFeedbackMsg . ' - '. $sFeedbackType);
			die('stop');
			*/
			
			$oForm->addErrorMessage("Form_SubscribeForm_error", $sFeedbackMsg, $sFeedbackType);
			Director::redirectBack();
		}
		
		
		function getValueFromData($data) {
			$result = '';
			$entries = (isset($data[$this->Name])) ? $data[$this->Name] : false;
			
			if($entries) {
				if(!is_array($data[$this->Name])) {
					$entries = array($data[$this->Name]);
				}
				foreach($entries as $selected => $value) {
					if(!$result) {
						$result = $value;
					} else {
						$result .= ", " . $value;
					}
				}
			}
			return $result;
		}		
		
	}
?>