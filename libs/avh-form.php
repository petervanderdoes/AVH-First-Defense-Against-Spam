<?php
if ( ! defined('AVH_FRAMEWORK')) {
	die('You are not allowed to call this page directly.');
}
if ( ! class_exists('AVH_Form')) {

	class AVH_Form {
		// @var Use tables to create Form
		private $use_table = false;
		private $option_name;
		private $nonce;

		/**
		 * Generates an opening HTML form tag.
		 *
		 * Form will submit back to the current page using POST
		 * echo Form::open();
		 *
		 * Form will submit to 'search' using GET
		 * echo Form::open('search', array('method' => 'get'));
		 *
		 * When "file" inputs are present, you must include the "enctype"
		 * echo Form::open(NULL, array('enctype' => 'multipart/form-data'));
		 *
		 * @param mixed $action     form action, defaults to the current request URI, or [Request] class to use
		 * @param array $attributes html attributes
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		public function open($action = null, array $attributes = null) {
			if ( ! isset($attributes['method'])) {
				// Use POST method
				$attributes['method'] = 'post';
			}

			return '<form action="' . $action . '"' . AVH_Common::attributes($attributes) . '>';
		}

		/**
		 * Creates the closing form tag.
		 *
		 * echo Form::close();
		 *
		 * @return string
		 */
		public function close() {
			return '</form>';
		}

		public function open_table() {
			$this->use_table = true;

			return "\n<table class='form-table'>\n";
		}

		public function close_table() {
			$this->use_table = false;

			return "\n</table>\n";
		}

		/**
		 * Create the nonce field.
		 * Instead of using the standard WordPress function, we duplicate the function but using the methods of this class.
		 * This will create a more standard looking HTML output.
		 *
		 * @param boolean $referer
		 *
		 * @return string
		 */
		public function nonce_field($referer = true) {
			$nonce_field = $this->hidden('_wpnonce', wp_create_nonce($this->nonce));
			if ($referer) {
				$ref = $_SERVER['REQUEST_URI'];
				$nonce_field .= $this->hidden('_wp_http_referer', $ref);
			}

			return $nonce_field;
		}

		public function settings_fields($action, $nonce) {
			$return = $this->hidden('action', $action);
			$return .= $this->nonce_field();

			return $return;
		}

		public function text($label_text, $description, $name, $value = null, array $attributes = null) {
			$label = $this->getLabel($name, $label_text);
			$input = $this->getInput($name, $value, $attributes);

			return $this->getOutput($label, $input);
		}

		public function checkboxes($label_text, $descripton, $name, array $options, array $attributes = null) {
			$label       = $this->getLabel($name, $label_text);
			$outputLabel = $this->getOutputLabel($label);
			$field       = '';
			foreach ($options as $value => $attr) {
				$is_checked = (isset($attr['checked']) ? $attr['checked'] : false);
				$field .= $this->getCheckbox($value, true, $is_checked, $attributes);
				$field .= $this->getLabel($value, $attr['text']);
				$field .= '<br>';
			}
			$outputLabel .= $this->getOutputField($field);

			return $outputLabel;
		}

		public function select(
			$label_text,
			$description,
			$name,
			array $options = null,
			$selected = null,
			array $attributes = null
		) {
			$label = $this->getLabel($name, $label_text);
			$field = $this->getSelect($name, $options, $selected, $attributes);

			return $this->getOutput($label, $field);
		}

		/**
		 * Creates a hidden form input.
		 *
		 * echo Form::hidden('csrf', $token);
		 *
		 * @param string $name       Input name
		 * @param string $value      Input value
		 * @param array  $attributes html attributes
		 * @param bool   $use_option_name
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		public function hidden($name, $value = null, array $attributes = null, $use_option_name = false) {
			$attributes['type'] = 'hidden';

			return $this->getInput($name, $value, $attributes, $use_option_name);
		}

		/**
		 * Creates a button form input.
		 * Note that the body of a button is NOT escaped,
		 * to allow images and other HTML to be used.
		 *
		 * echo Form::button('save', 'Save Profile', array('type' => 'submit'));
		 *
		 * @param string $name       Input name
		 * @param string $body       Input value
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		public function button($name, $body, array $attributes = null) {
			// Set the input name
			$attributes['name'] = $name;

			return '<button' . AVH_Common::attributes($attributes) . '>' . $body . '</button>';
		}

		/**
		 * Creates a submit form input.
		 *
		 * echo Form::submit(NULL, 'Login');
		 *
		 * @param string $name       Input name
		 * @param string $value      Input value
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses Form::getInput
		 */
		public function submit($name, $value, array $attributes = null) {
			$attributes['type'] = 'submit';

			return '<p class="submit">' . $this->getInput($name, $value, $attributes) . '</p>';
		}

		// ____________PRIVATE FUNCTIONS____________

		/**
		 * Creates a form input.
		 * If no type is specified, a "text" type input will
		 * be returned.
		 *
		 * echo Form::getInput('username', $username);
		 *
		 * @param string $name       Input name
		 * @param string $value      Input value
		 * @param array  $attributes html attributes
		 *
		 * @param bool   $use_option_name
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		private function getInput($name, $value = null, array $attributes = null, $use_option_name = true) {
			// Set the input name
			if (isset($this->option_name) && $use_option_name) {
				$attributes['name'] = $this->option_name . '[' . $name . ']';
			} else {
				$attributes['name'] = $name;
			}

			// Set the input value
			$attributes['value'] = $value;

			if ( ! isset($attributes['type'])) {
				// Default type is text
				$attributes['type'] = 'text';
			}

			if ( ! isset($attributes['id'])) {
				$attributes['id'] = $name;
			}

			return '<input' . AVH_Common::attributes($attributes) . ' />';
		}

		/**
		 * Creates a password form input.
		 *
		 * echo Form::getPassword('getPassword');
		 *
		 * @param string $name       Input name
		 * @param string $value      Input value
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		private function getPassword($name, $value = null, array $attributes = null) {
			$attributes['type'] = 'password';

			return $this->getInput($name, $value, $attributes);
		}

		/**
		 * Creates a file upload form input.
		 * No input value can be specified.
		 *
		 * echo Form::getFile('image');
		 *
		 * @param string $name       Input name
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		private function getFile($name, array $attributes = null) {
			$attributes['type'] = 'file';

			return $this->getInput($name, null, $attributes);
		}

		/**
		 * Creates a checkbox form input.
		 *
		 * echo Form::getCheckbox('remember_me', 1, (bool) $remember);
		 *
		 * @param string  $name       Input name
		 * @param string  $value      Input value
		 * @param boolean $checked    checked status
		 * @param array   $attributes html attributes
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		private function getCheckbox($name, $value = null, $checked = false, array $attributes = null) {
			$attributes['type'] = 'checkbox';

			if ($checked === true) {
				// Make the checkbox active
				$attributes[] = 'checked';
			}

			return $this->getInput($name, $value, $attributes);
		}

		/**
		 * Creates a radio form input.
		 *
		 * echo Form::getRadio('like_cats', 1, $cats);
		 * echo Form::getRadio('like_cats', 0, ! $cats);
		 *
		 * @param string  $name       Input name
		 * @param string  $value      Input value
		 * @param boolean $checked    checked status
		 * @param array   $attributes html attributes
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		private function getRadio($name, $value = null, $checked = false, array $attributes = null) {
			$attributes['type'] = 'radio';

			if ($checked === true) {
				// Make the radio active
				$attributes[] = 'checked';
			}

			return $this->getInput($name, $value, $attributes);
		}

		/**
		 * Creates a textarea form input.
		 *
		 * echo Form::getTextarea('about', $about);
		 *
		 * @param string  $name          Textarea name
		 * @param string  $body          Textarea body
		 * @param array   $attributes    html attributes
		 * @param boolean $double_encode encode existing HTML characters
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		private function getTextarea($name, $body = '', array $attributes = null, $double_encode = true) {
			// Set the input name
			$attributes['name'] = $name;

			// Add default rows and cols attributes (required)
			$attributes += array('rows' => 10, 'cols' => 50);

			return '<textarea' . AVH_Common::attributes($attributes) . '>' . esc_textarea($body) . '</getTextarea>';
		}

		/**
		 * Creates a select form input.
		 *
		 * echo Form::select('country', $countries, $country);
		 *
		 * [!!] Support for multiple selected options was added in v3.0.7.
		 *
		 * @param string $name       Input name
		 * @param array  $options    available options
		 * @param mixed  $selected   selected option string, or an array of selected options
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		private function getSelect($name, array $options = null, $selected = null, array $attributes = null) {
			// Set the input name
			if (isset($this->option_name)) {
				$attributes['name'] = $this->option_name . '[' . $name . ']';
			} else {
				$attributes['name'] = $name;
			}

			if (is_array($selected)) {
				// This is a multi-select, god save us!
				$attributes[] = 'multiple';
			}

			if ( ! is_array($selected)) {
				if ($selected === null) {
					// Use an empty array
					$selected = array();
				} else {
					// Convert the selected options to an array
					$selected = array((string) $selected);
				}
			}

			if (empty($options)) {
				// There are no options
				$options = '';
			} else {
				foreach ($options as $value => $name) {
					if (is_array($name)) {
						// Create a new optgroup
						$group = array('label' => $value);

						// Create a new list of options
						$new_options = array();

						foreach ($name as $name_key => $name_value) {
							// Force value to be string
							$name_key = (string) $name_key;

							// Create a new attribute set for this option
							$option = array('value' => $name_key);

							if (in_array($name_key, $selected)) {
								// This option is selected
								$option[] = 'selected';
							}

							// Change the option to the HTML string
							$new_options[] = '<option' .
							                 AVH_Common::attributes($option) .
							                 '>' .
							                 esc_html($name) .
							                 '</option>';
						}

						// Compile the options into a string
						$new_options = "\n" . implode("\n", $new_options) . "\n";

						$options[ $value ] = '<optgroup' .
						                     AVH_Common::attributes($group) .
						                     '>' .
						                     $new_options .
						                     '</optgroup>';
					} else {
						// Force value to be string
						$value = (string) $value;

						// Create a new attribute set for this option
						$option = array('value' => $value);

						if (in_array($value, $selected)) {
							// This option is selected
							$option[] = 'selected';
						}

						// Change the option to the HTML string
						$options[ $value ] = '<option' .
						                     AVH_Common::attributes($option) .
						                     '>' .
						                     esc_html($name) .
						                     '</option>';
					}
				}

				// Compile the options into a single string
				$options = "\n" . implode("\n", $options) . "\n";
			}

			return '<select' . AVH_Common::attributes($attributes) . '>' . $options . '</select>';
		}

		/**
		 * Creates a image form input.
		 *
		 * echo Form::image(NULL, NULL, array('src' => 'media/img/login.png'));
		 *
		 * @param string  $name       Input name
		 * @param string  $value      Input value
		 * @param array   $attributes html attributes
		 * @param boolean $index      add index file to URL?
		 *
		 * @return string
		 * @uses $this->getInput
		 */
		private function getImage($name, $value, array $attributes = null, $index = false) {
			if ( ! empty($attributes['src'])) {
				if (strpos($attributes['src'], '://') === false) {
					// Add the base URL
					$attributes['src'] = URL::base($index) . $attributes['src'];
				}
			}

			$attributes['type'] = 'image';

			return $this->getInput($name, $value, $attributes);
		}

		/**
		 * Creates a form label.
		 * Label text is not automatically translated.
		 *
		 * echo Form::label('username', 'Username');
		 *
		 * @param string $input      target input
		 * @param string $text       label text
		 * @param array  $attributes html attributes
		 *
		 * @return string
		 * @uses AVH_Common::attributes
		 */
		private function getLabel($input, $text = null, array $attributes = null) {
			if ($text === null) {
				// Use the input name as the text
				$text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
			}

			// Set the label target
			$attributes['for'] = $input;

			return '<label' . AVH_Common::attributes($attributes) . '>' . $text . '</label>';
		}

		private function getOutput($label, $field) {
			$return = $this->getOutputLabel($label);
			$return .= $this->getOutputField($field);

			return $return;
		}

		private function getOutputLabel($label) {
			if ($this->use_table) {
				return "\n<tr>\n\t<th scope='row'>" . $label . "</th>";
			} else {
				return "\n" . $label;
			}
		}

		private function getOutputField($field) {
			if ($this->use_table) {
				return "\n\t<td>\n\t\t" . $field . "\n\t</td>";
			} else {
				return "\n" . $field;
			}
		}

		// __________________________________________
		// ____________Setter and Getters____________
		// __________________________________________
		/**
		 *
		 * @param $option_name field_type
		 */
		public function setOption_name($option_name) {
			$this->option_name = $option_name;
		}

		public function getOption_name() {
			return $this->option_name;
		}

		public function setNonce_action($nonce) {
			$this->nonce = $this->option_name . '-' . $nonce;
		}

		public function getNonce_action() {
			return $this->nonce;
		}
	} // End form
}
