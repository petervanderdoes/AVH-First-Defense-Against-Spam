<?php
if (!defined('AVH_FRAMEWORK')) {
    die('You are not allowed to call this page directly.');
}
if (!class_exists('AVH_Form')) {

    class AVH_Form
    {
        // @var Use tables to create Form
        private $_use_table = false;
        private $_option_name;
        private $_nonce;

        /**
         * Generates an opening HTML form tag.
         *
         * // Form will submit back to the current page using POST
         * echo Form::open();
         *
         * // Form will submit to 'search' using GET
         * echo Form::open('search', array('method' => 'get'));
         *
         * // When "file" inputs are present, you must include the "enctype"
         * echo Form::open(NULL, array('enctype' => 'multipart/form-data'));
         *
         * @param mixed $action     form action, defaults to the current request URI, or [Request] class to use
         * @param array $attributes html attributes
         *
         * @return string
         * @uses AVH_Common::attributes
         */
        public function open($action = null, array $attributes = null)
        {
            if (!isset($attributes['method'])) {
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
        public function close()
        {
            return '</form>';
        }

        public function open_table()
        {
            $this->_use_table = true;

            return "\n<table class='form-table'>\n";
        }

        public function close_table()
        {
            $this->_use_table = false;

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
        public function nonce_field($referer = true)
        {
            $nonce_field = $this->hidden('_wpnonce', wp_create_nonce($this->_nonce));
            if ($referer) {
                $ref = $_SERVER['REQUEST_URI'];
                $nonce_field .= $this->hidden('_wp_http_referer', $ref);
            }

            return $nonce_field;
        }

        public function settings_fields($action, $nonce)
        {
            $_return = $this->hidden('action', $action);
            $_return .= $this->nonce_field();

            return $_return;
        }

        public function text($label, $description, $name, $value = null, array $attributes = null)
        {
            $_label = $this->_label($name, $label);
            $_field = $this->_input($name, $value, $attributes);

            return $this->_output($_label, $_field);
        }

        public function checkboxes($label, $descripton, $name, array $options, array $attributes = null)
        {
            $_label = $this->_label($name, $label);
            $_return = $this->_output_label($_label);
            $_field = '';
            foreach ($options as $value => $attr) {
                $_checked = (isset($attr['checked']) ? $attr['checked'] : false);
                $_field .= $this->_checkbox($value, true, $_checked, $attributes);
                $_field .= $this->_label($value, $attr['text']);
                $_field .= '<br>';
            }
            $_return .= $this->_output_field($_field);

            return $_return;
        }

        public function select(
            $label,
            $description,
            $name,
            array $options = null,
            $selected = null,
            array $attributes = null
        ) {
            $_label = $this->_label($name, $label);
            $_field = $this->_select($name, $options, $selected, $attributes);

            return $this->_output($_label, $_field);
        }

        /**
         * Creates a hidden form input.
         *
         * echo Form::hidden('csrf', $token);
         *
         * @param string $name       input name
         * @param string $value      input value
         * @param array  $attributes html attributes
         * @param bool   $use_option_name
         *
         * @return string
         * @uses $this->input
         */
        public function hidden($name, $value = null, array $attributes = null, $use_option_name = false)
        {
            $attributes['type'] = 'hidden';

            return $this->_input($name, $value, $attributes, $use_option_name);
        }

        /**
         * Creates a button form input.
         * Note that the body of a button is NOT escaped,
         * to allow images and other HTML to be used.
         *
         * echo Form::button('save', 'Save Profile', array('type' => 'submit'));
         *
         * @param string $name       input name
         * @param string $body       input value
         * @param array  $attributes html attributes
         *
         * @return string
         * @uses AVH_Common::attributes
         */
        public function button($name, $body, array $attributes = null)
        {
            // Set the input name
            $attributes['name'] = $name;

            return '<button' . AVH_Common::attributes($attributes) . '>' . $body . '</button>';
        }

        /**
         * Creates a submit form input.
         *
         * echo Form::submit(NULL, 'Login');
         *
         * @param string $name       input name
         * @param string $value      input value
         * @param array  $attributes html attributes
         *
         * @return string
         * @uses Form::input
         */
        public function submit($name, $value, array $attributes = null)
        {
            $attributes['type'] = 'submit';

            return '<p class="submit">' . $this->_input($name, $value, $attributes) . '</p>';
        }

        // ____________PRIVATE FUNCTIONS____________

        /**
         * Creates a form input.
         * If no type is specified, a "text" type input will
         * be returned.
         *
         * echo Form::input('username', $username);
         *
         * @param string $name       input name
         * @param string $value      input value
         * @param array  $attributes html attributes
         *
         * @param bool   $use_option_name
         *
         * @return string
         * @uses AVH_Common::attributes
         */
        private function _input($name, $value = null, array $attributes = null, $use_option_name = true)
        {
            // Set the input name
            if (isset($this->_option_name) && $use_option_name) {
                $attributes['name'] = $this->_option_name . '[' . $name . ']';
            } else {
                $attributes['name'] = $name;
            }

            // Set the input value
            $attributes['value'] = $value;

            if (!isset($attributes['type'])) {
                // Default type is text
                $attributes['type'] = 'text';
            }

            if (!isset($attributes['id'])) {
                $attributes['id'] = $name;
            }

            return '<input' . AVH_Common::attributes($attributes) . ' />';
        }

        /**
         * Creates a password form input.
         *
         * echo Form::password('password');
         *
         * @param string $name       input name
         * @param string $value      input value
         * @param array  $attributes html attributes
         *
         * @return string
         * @uses $this->input
         */
        private function _password($name, $value = null, array $attributes = null)
        {
            $attributes['type'] = 'password';

            return $this->_input($name, $value, $attributes);
        }

        /**
         * Creates a file upload form input.
         * No input value can be specified.
         *
         * echo Form::file('image');
         *
         * @param string $name       input name
         * @param array  $attributes html attributes
         *
         * @return string
         * @uses $this->input
         */
        private function _file($name, array $attributes = null)
        {
            $attributes['type'] = 'file';

            return $this->_input($name, null, $attributes);
        }

        /**
         * Creates a checkbox form input.
         *
         * echo Form::checkbox('remember_me', 1, (bool) $remember);
         *
         * @param string  $name       input name
         * @param string  $value      input value
         * @param boolean $checked    checked status
         * @param array   $attributes html attributes
         *
         * @return string
         * @uses $this->input
         */
        private function _checkbox($name, $value = null, $checked = false, array $attributes = null)
        {
            $attributes['type'] = 'checkbox';

            if ($checked === true) {
                // Make the checkbox active
                $attributes[] = 'checked';
            }

            return $this->_input($name, $value, $attributes);
        }

        /**
         * Creates a radio form input.
         *
         * echo Form::radio('like_cats', 1, $cats);
         * echo Form::radio('like_cats', 0, ! $cats);
         *
         * @param string  $name       input name
         * @param string  $value      input value
         * @param boolean $checked    checked status
         * @param array   $attributes html attributes
         *
         * @return string
         * @uses $this->input
         */
        private function _radio($name, $value = null, $checked = false, array $attributes = null)
        {
            $attributes['type'] = 'radio';

            if ($checked === true) {
                // Make the radio active
                $attributes[] = 'checked';
            }

            return $this->_input($name, $value, $attributes);
        }

        /**
         * Creates a textarea form input.
         *
         * echo Form::textarea('about', $about);
         *
         * @param string  $name          textarea name
         * @param string  $body          textarea body
         * @param array   $attributes    html attributes
         * @param boolean $double_encode encode existing HTML characters
         *
         * @return string
         * @uses AVH_Common::attributes
         */
        private function _textarea($name, $body = '', array $attributes = null, $double_encode = true)
        {
            // Set the input name
            $attributes['name'] = $name;

            // Add default rows and cols attributes (required)
            $attributes += array('rows' => 10, 'cols' => 50);

            return '<textarea' . AVH_Common::attributes($attributes) . '>' . esc_textarea($body) . '</textarea>';
        }

        /**
         * Creates a select form input.
         *
         * echo Form::select('country', $countries, $country);
         *
         * [!!] Support for multiple selected options was added in v3.0.7.
         *
         * @param string $name       input name
         * @param array  $options    available options
         * @param mixed  $selected   selected option string, or an array of selected options
         * @param array  $attributes html attributes
         *
         * @return string
         * @uses AVH_Common::attributes
         */
        private function _select($name, array $options = null, $selected = null, array $attributes = null)
        {
            // Set the input name
            if (isset($this->_option_name)) {
                $attributes['name'] = $this->_option_name . '[' . $name . ']';
            } else {
                $attributes['name'] = $name;
            }

            if (is_array($selected)) {
                // This is a multi-select, god save us!
                $attributes[] = 'multiple';
            }

            if (!is_array($selected)) {
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
                        $_options = array();

                        foreach ($name as $_value => $_name) {
                            // Force value to be string
                            $_value = (string) $_value;

                            // Create a new attribute set for this option
                            $option = array('value' => $_value);

                            if (in_array($_value, $selected)) {
                                // This option is selected
                                $option[] = 'selected';
                            }

                            // Change the option to the HTML string
                            $_options[] = '<option' . AVH_Common::attributes($option) . '>' . esc_html(
                                    $name
                                ) . '</option>';
                        }

                        // Compile the options into a string
                        $_options = "\n" . implode("\n", $_options) . "\n";

                        $options[$value] = '<optgroup' . AVH_Common::attributes(
                                $group
                            ) . '>' . $_options . '</optgroup>';
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
                        $options[$value] = '<option' . AVH_Common::attributes($option) . '>' . esc_html(
                                $name
                            ) . '</option>';
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
         * @param string  $name       input name
         * @param string  $value      input value
         * @param array   $attributes html attributes
         * @param boolean $index      add index file to URL?
         *
         * @return string
         * @uses $this->input
         */
        private function _image($name, $value, array $attributes = null, $index = false)
        {
            if (!empty($attributes['src'])) {
                if (strpos($attributes['src'], '://') === false) {
                    // Add the base URL
                    $attributes['src'] = URL::base($index) . $attributes['src'];
                }
            }

            $attributes['type'] = 'image';

            return $this->_input($name, $value, $attributes);
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
        private function _label($input, $text = null, array $attributes = null)
        {
            if ($text === null) {
                // Use the input name as the text
                $text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
            }

            // Set the label target
            $attributes['for'] = $input;

            return '<label' . AVH_Common::attributes($attributes) . '>' . $text . '</label>';
        }

        private function _output($label, $field)
        {
            $_return = $this->_output_label($label);
            $_return .= $this->_output_field($field);

            return $_return;
        }

        private function _output_label($label)
        {
            if ($this->_use_table) {
                return "\n<tr>\n\t<th scope='row'>" . $label . "</th>";
            } else {
                return "\n" . $label;
            }
        }

        private function _output_field($field)
        {
            if ($this->_use_table) {
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
         * @param $_option_name field_type
         */
        public function setOption_name($_option_name)
        {
            $this->_option_name = $_option_name;
        }

        public function getOption_name()
        {
            return $this->_option_name;
        }

        public function setNonce_action($_nonce)
        {
            $this->_nonce = $this->_option_name . '-' . $_nonce;
        }

        public function getNonce_action()
        {
            return $this->_nonce;
        }
    } // End form
}
