<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if (!class_exists('Persian_Woocommerce_Address')) :

    class Persian_Woocommerce_Address extends Persian_Woocommerce_Plugin
    {

        protected $states;
        private $fields = array();
        private $Country = 'IR';
        private $selected_city = array();
        private static $is_run;

        public function __construct()
        {

            add_filter('woocommerce_get_country_locale', array($this, 'locales'));
            add_filter('woocommerce_localisation_address_formats', array($this, 'address_formats'));
            add_filter('woocommerce_states', array($this, 'iran_states'), 10, 1);

            if (PW()->get_options('enable_iran_cities') == 'yes') {

                add_filter('woocommerce_checkout_fields', array($this, 'checkout_fields_cities'));
                add_filter('woocommerce_form_field_billing_iran_cities', array($this, 'iran_cities_field'), 11, 4);
                add_filter('woocommerce_form_field_shipping_iran_cities', array($this, 'iran_cities_field'), 11, 4);

                add_action('woocommerce_after_order_notes', array($this, 'inline_js'));
                add_action('wp_footer', array($this, 'inline_js'));
                add_action('wp_enqueue_scripts', array($this, 'external_js'));

            }

            $this->states = array(
                'AL' => 'البرز',
                'AR' => 'اردبیل',
                'AE' => 'آذربایجان شرقی',
                'AW' => 'آذربایجان غربی',
                'BU' => 'بوشهر',
                'CM' => 'چهارمحال و بختیاری',
                'FA' => 'فارس',
                'GI' => 'گیلان',
                'GO' => 'گلستان',
                'HD' => 'همدان',
                'HG' => 'هرمزگان',
                'IL' => 'ایلام',
                'IS' => 'اصفهان',
                'KE' => 'کرمان',
                'BK' => 'کرمانشاه',
                'KS' => 'خراسان شمالی',
                'KV' => 'خراسان رضوی',
                'KJ' => 'خراسان جنوبی',
                'KZ' => 'خوزستان',
                'KB' => 'کهگیلویه و بویراحمد',
                'KD' => 'کردستان',
                'LO' => 'لرستان',
                'MK' => 'مرکزی',
                'MN' => 'مازندران',
                'QZ' => 'قزوین',
                'QM' => 'قم',
                'SM' => 'سمنان',
                'SB' => 'سیستان و بلوچستان',
                'TE' => 'تهران',
                'YA' => 'یزد',
                'ZA' => 'زنجان'
            );
        }

        public function locales($locales)
        {
            $locales[$this->Country] = array(
                'state' => array('label' => __('Province', 'woocommerce')),
                'postcode' => array('label' => __('Postcode', 'woocommerce'))
            );
            return $locales;
        }

        public function address_formats($formats)
        {
            $formats[$this->Country] = "{company}\n{first_name} {last_name}\n{country}\n{state}\n{city}\n{address_1} - {address_2}\n{postcode}";
            return $formats;
        }

        public function iran_states($states)
        {
            $states['IR'] = $this->states;

            if (PW()->get_options("allowed_states") == "all")
                return $states;

            $selections = PW()->get_options('specific_allowed_states');

            if (is_array($selections))
                $states['IR'] = array_intersect_key($this->states, array_flip($selections));

            return $states;
        }

        //Cities
        public function checkout_fields_cities($fields)
        {
            $this->fields = $fields;

            $types = array('billing', 'shipping');
            foreach ($types as $type) {
                $city_classes = '';
                if (!empty($fields[$type][$type . '_city']['class']) && $city_classes = $fields[$type][$type . '_city']['class']) {
                    $city_classes = is_array($city_classes) ? implode(',', $city_classes) : $city_classes;
                    $city_classes = str_ireplace('form-row-wide', 'form-row-last', $city_classes);
                }
                $fields[$type][$type . '_city']['type'] = apply_filters($type . '_iran_city_type', $type . '_iran_cities', $fields);
                $fields[$type][$type . '_city']['class'] = apply_filters($type . '_iran_city_class', explode(',', $city_classes), $fields);
                $fields[$type][$type . '_city']['options'] = apply_filters($type . '_iran_city_options', array('' => ''), $fields);
            }

            return $fields;
        }

        public function iran_cities_field($field, $key, $args, $value)
        {

            $type = explode('_', $args['type']);
            if (!empty($value))
                $this->selected_city[] = $value . '_vsh_' . $type[0];

            $required = $args['required'] ? ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce') . '">*</abbr>' : '';

            $args['label_class'] = array();
            if (is_string($args['label_class']))
                $args['label_class'] = array($args['label_class']);

            if (is_null($value))
                $value = !empty($args['default']) ? $args['default'] : '';

            $selected_value = $args['type'] . '_selected_value';
            global ${$selected_value};
            ${$selected_value} = $value;

            $custom_attributes = array();
            if (!empty($args['custom_attributes']) && is_array($args['custom_attributes']))
                foreach ($args['custom_attributes'] as $attribute => $attribute_value)
                    $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';

            if (!empty($args['validate']))
                foreach ($args['validate'] as $validate)
                    $args['class'][] = 'validate-' . $validate;

            $args['placeholder'] = __('یک شهر انتخاب کنید', 'woocommerce');

            $label_id = $args['id'];
            $field_container = '<p class="form-row %1$s" id="%2$s">%3$s</p>';

            $field = '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="state_select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' placeholder="' . esc_attr($args['placeholder']) . '"></select>';

            $field_html = '';

            if ($args['label'] && 'checkbox' != $args['type'])
                $field_html .= '<label for="' . esc_attr($label_id) . '" class="' . esc_attr(implode(' ', $args['label_class'])) . '">' . $args['label'] . $required . '</label>';

            $field_html .= $field;

            if ($args['description'])
                $field_html .= '<span class="description">' . esc_attr($args['description']) . '</span>';

            $container_class = 'form-row ' . esc_attr(implode(' ', $args['class']));
            $container_id = esc_attr($args['id']) . '_field';

            $after = !empty($args['clear']) ? '<div class="clear"></div>' : '';

            $iran_cities = sprintf($field_container, $container_class, $container_id, $field_html) . $after;

            return apply_filters('iran_cities_filed_select_input', $iran_cities, $field_container, $container_class, $container_id, $field_html, $field, $key, $args, $value, $after);
        }

        public function external_js()
        {

            wp_dequeue_script('pw-iran-cities');
            wp_deregister_script('pw-iran-cities');
            wp_register_script('pw-iran-cities', apply_filters('persian_woo_iran_cities', PW()->plugin_url('include/assets/js/iran_cities.min.js')), array('jquery'), PW_VERSION, true);

            if (is_checkout()) {
                wp_enqueue_script('pw-iran-cities');
            }
        }

        public function inline_js()
        {
            if (!empty(self::$is_run) || !is_checkout())
                return true;

            self::$is_run = 'applied';

            $value_index = apply_filters('iran_cities_value_index', 0);

            $types = array('billing', 'shipping');
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    <?php
                    foreach ($types as $type) :
                    $selected_value = $type . '_iran_cities_selected_value';
                    global ${$selected_value};
                    $value = !empty(${$selected_value}) ? ${$selected_value} : '';
                    $placeholder = isset($this->fields[$type][$type . '_city']['placeholder']) ? $this->fields[$type][$type . '_city']['placeholder'] : __('City', 'woocommerce');
                    ?>
                    $(document.body).on('change', '#<?php echo $type; ?>_state', function () {

                        if ($('#<?php echo $type; ?>_country').val() == '<?php echo $this->Country ?>') {
                            <?php echo $type; ?>_cities = [];
                            <?php echo $type; ?>_cities[0] = new Array('خطا در دریافت شهرها', '0');
                            if (typeof Persian_Woo_iranCities === "function")
                                <?php echo $type; ?>_cities = Persian_Woo_iranCities($('#<?php echo $type; ?>_state').val());
                            else {
                                alert('تابع مربوط به شهرها یافت نمیشود. با مدیریت در میان بگذارید.');
                            }

                            <?php echo $type; ?>_cities.sort(function (a, b) {
                                if (a[0] == b[0])
                                    return 0;
                                if (a[0] > b[0])
                                    return 1;
                                else
                                    return -1;
                            });

                            var options = '<option value="-1">انتخاب کنید</option>';
                            var j;
                            <?php echo $type; ?>_selected = '';
                            for (j in <?php echo $type; ?>_cities) {
                                selected = '';
                                if (<?php echo $type; ?>_cities[j][<?php echo $value_index; ?>] == '<?php echo $value;?>') {
                                    selected = "selected";
                                    <?php echo $type; ?>_selected = '<?php echo $value;?>';
                                }
                                options += "<option value='" + <?php echo $type; ?>_cities[j][<?php echo $value_index; ?>] + "' " + selected + ">" + <?php echo $type; ?>_cities[j][0] + "</option>";
                            }

                            $('#<?php echo $type; ?>_city').empty();

                            if ($("#<?php echo $type; ?>_city").is('select')) {
                                $('#<?php echo $type; ?>_city').append(options);
                            }

                            $('#<?php echo $type; ?>_city').val(<?php echo $type; ?>_selected).trigger("change");
                        }
                    });

                    var <?php echo $type; ?>_city_select = $('#<?php echo $type; ?>_city_field').html();
                    var <?php echo $type; ?>_city_input = '<input id="<?php echo $type; ?>_city" name="<?php echo $type; ?>_city" type="text" class="input-text" value="" placeholder="<?php echo $placeholder;?>" />';

                    $('#<?php echo $type; ?>_country').change(function () {

                        if ($('#<?php echo $type; ?>_country').val() == '<?php echo $this->Country ?>') {
                            if (!$("#<?php echo $type; ?>_city").is('select')) {
                                $('#<?php echo $type; ?>_city_field').empty();
                                $('#<?php echo $type; ?>_city_field').html(<?php echo $type; ?>_city_select);

                                $('#<?php echo $type; ?>_state').val('').trigger("change");
                                $('#<?php echo $type; ?>_city').val('').trigger("change");
                            }
                        }
                        else {
                            $('#<?php echo $type; ?>_city_field').find('*').not('label').remove();
                            $('#<?php echo $type; ?>_city_field').append(<?php echo $type; ?>_city_input);

                            $('#<?php echo $type; ?>_state').val('').trigger("change");
                            $('#<?php echo $type; ?>_city').val('').trigger("change");
                        }
                    });
                    <?php endforeach; ?>
                });
            </script>
            <?php
        }

    }
endif;

PW()->iran_address = new Persian_Woocommerce_Address();
?>