<?php
	class NewsletterWidget extends Widget {
		static $db = array(
			'ButtonSignup' => 'Boolean', 
			'LinkSignup' => 'Boolean'
		);
		static $defaults = array(
			'ButtonSignup' => 1, 
			'LinkSignup' => 1
		);

		static $title = 'Nieuwsbrief';
		static $cmsTitle = 'Nieuwsbrief';
		static $description = 'Blokje met inschrijfknop en -link voor de nieuwsbrief en de mogelijkheid om de laatste nieuwsbrief te lezen.';

		function getCMSFields() {
			$oFields = new FieldSet(
				new CheckboxField('ButtonSignup', 'Toon knop voor inschrijven'), 
				new CheckboxField('LinkSignup', 'Toon link voor inschrijven')
			);

			$oFields->merge(parent::getCMSFields());
			return $oFields;
		}


		// Retrieve the Page DataObject assigned to the MailChimp form template
		public function MailChimpPage() {
			$oPage = DataObject::get_one("MailChimpForm");

			if (!$oPage) {
				return false;
			} else {
				return $oPage;
			}
		}
	}
?>