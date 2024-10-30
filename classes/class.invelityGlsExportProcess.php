<?php

class InvelityGlsConnectProcess
{
    private $launcher;
    private $options;
    public $successful = array();
    public $unsuccessful = array();

    /**
     * Loads plugin textdomain and sets the options attribute from database
     */
    public function __construct(InvelityGlsConnect $launecher)
    {
        $this->launcher = $launecher;
        load_plugin_textdomain($this->launcher->getPluginSlug(), false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->options = get_option('invelity_gls_export_options');
        add_action('admin_footer-edit.php', array(&$this, 'export_gls_bulk_admin_footer'));
        add_action('load-edit.php', array(&$this, 'export_gls_bulk_action'));
        add_action('admin_notices', array(&$this, 'export_gls_bulk_admin_notices'));
    }

    /**
     * Adds option to export invoices to orders page bulk select
     */
    function export_gls_bulk_admin_footer()
    {
        global $post_type;

        if ($post_type == 'shop_order') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery('<option>').val('exportglsconnect').text('<?php _e('Export GLS connect', 'export_gls_bulk_textdomain')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('exportglsconnect').text('<?php _e('Export GLS connect', 'export_gls_bulk_textdomain')?>').appendTo("select[name='action2']");
                });
            </script>
            <?php
        }
    }

    /**
     * Sets up action to be taken after export option is selected
     * If export is selected, provides export and refreshes page
     * After refresh, notices are shown
     */
    function export_gls_bulk_action()
    {
        global $typenow;
        $post_type = $typenow;
        if ($post_type == 'shop_order') {
            // get the action
            $wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();
            $allowed_actions = array("exportglsconnect");
            if (!in_array($action, $allowed_actions)) return;
            // security check
            check_admin_referer('bulk-posts');

            // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
            if (isset($_REQUEST['post'])) {
                $post_ids = array_map('intval', $_REQUEST['post']);
            }

            if (empty($post_ids)) return;

            // this is based on wp-admin/edit.php
            $sendback = remove_query_arg(array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer());
            if (!$sendback)
                $sendback = admin_url("edit.php?post_type=$post_type");

            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg('paged', $pagenum, $sendback);

            switch ($action) {
                case 'exportglsconnect':

                    // set up user permissions/capabilities
                    if (!current_user_can('administrator')) {
                        wp_die(__('You are not allowed to export this data.', 'export_gls_bulk_textdomain'));
                    }

                    $exported = 0;
                    $list = array();
                    $list[] = array('dobierka', 'Odosielatel-nazov', 'Odosielatel-adresa', 'Odosielatel-PSC', 'Odosielatel-mesto', 'meno', 'adresa', 'mesto', 'psc', 'stat', 'tel', 'kontaktna_osoba', 'e-mail', 'pocet_balikov', 'obsah','vaha','variabilny_symbol','referencne_cislo','sluzby','GLS ID');

                    foreach ($post_ids as $post_id) {

                        if (!$this->perform_export_loop_item($post_id)) {
                            wp_die(__('Error exporting order.', 'export_gls_bulk_textdomain'));
                        } else {
                            $list[] = $this->perform_export_loop_item($post_id);
                        }

                        $exported++;
                    }

                    $sendback = add_query_arg(array('exported' => $exported, 'ids' => join(',', $post_ids)), $sendback);
                    break;

                default:
                    return;
            }

            $sendback = remove_query_arg(array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'), $sendback);

// create a file pointer connected to the output stream
            $upload_dir = wp_upload_dir();
            $dir = $upload_dir['basedir'] . '/datagls';
            if (!is_dir($dir)) {
                mkdir($dir);
            };
            $filename = $dir . '/datagls.csv';
            $fp = fopen($filename, 'w');
            fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF))); //oprava excel kodovania
            foreach ($list as $fields) {
                fputcsv($fp, $fields, ';');
            } //row values
            fclose($fp);

// output the column headings
            wp_redirect($sendback);
            exit();
        }
    }


    /**
     * Dsisplays the notice
     */
    function export_gls_bulk_admin_notices()
    {
        global $post_type, $pagenow;
        $upload_dir = wp_upload_dir();
        $filename = $upload_dir['baseurl'] . '/datagls/datagls.csv';
        if ($pagenow == 'edit.php' && $post_type == 'shop_order' && isset($_REQUEST['exported']) && (int)$_REQUEST['exported']) {
            $message = sprintf(_n('Order exported.', '%s orders exported.', $_REQUEST['exported'], 'export_gls_bulk_textdomain'), number_format_i18n($_REQUEST['exported']));
            echo "<div class=\"updated\"><p>{$message} <a target=\"_blank\" style=\"text-decoration:none;\" href=\"" . $filename . "?" . time() . "\"><span class=\"dashicons dashicons-media-spreadsheet\"></span>" . __('Open file', 'export_gls_bulk_textdomain') . "</a></p></div>";
        }
    }

    function perform_export_loop_item($post_id)
    {
        // do whatever work needs to be done / loop
        $arr = array();
        $order = new WC_Order($post_id);

	    if($order->get_payment_method() == 'cod') {
		    $arr['dobierka'] = $order->order_total;
	    }else{
		    $arr['dobierka'] = 0;
	    }
       
        $arr['Odosielatel-nazov'] = $this->options['sender_name'];
        $arr['Odosielatel-adresa'] =  $this->options['sender_address'];
        $arr['Odosielatel-PSC'] =  $this->options['sender_zip'];
        $arr['Odosielatel-mesto'] =  $this->options['sender_city'];
    
    

        $arr['meno'] =  $order->billing_first_name . ' ' . $order->billing_last_name;

        if (trim($order->shipping_address_1) <> '') {
            $arr['adresa'] = $order->shipping_address_1;
        } else {
            $arr['adresa'] = $order->billing_address_1;
        }
        if (trim($order->shipping_city) <> '') {
            $arr['mesto'] = $order->shipping_city;
        } else {
            $arr['mesto'] = $order->billing_city;
        }
        if (trim($order->shipping_postcode) <> '') {
            $arr['psc'] = $order->shipping_postcode;
        } else {
            $arr['psc'] = $order->billing_postcode;
        }
        if (trim($order->shipping_country) <> '') {
            $arr['stat'] = $order->shipping_country;
        } else {
            $arr['stat'] = $order->billing_country;
        }
        if (trim($order->shipping_phone) <> '') {
            $arr['tel'] = $order->shipping_phone;
        } else {
            $arr['tel'] = $order->billing_phone;
        }
        $arr['kontaktna_osoba'] =  $order->billing_first_name . ' ' . $order->billing_last_name;
      
        $arr['e-mail'] = $order->billing_email;
        $arr['pocet_balikov'] ='1';
        $arr['obsah'] = '';

        if($this->getTotalWeight($order)!= 0){
            $arr['vaha']= $this->getTotalWeight( $order);
        }else{
            $arr['vaha']='';
        }

        $arr['variabilny_symbol'] = $order->get_order_number();
        $arr['referencne_cislo'] = '';
        $arr['sluzby'] = '';
        $arr['GLS ID'] = '';
        if (count($arr) == 0) {
            return array();
        }
        return $arr;
    }

    function getTotalWeight( $order){
        $total_weight = 0;

        foreach( $order->get_items() as $item_id => $product_item ){
            $quantity = $product_item->get_quantity(); // get quantity
            $product = $product_item->get_product(); // get the WC_Product object
            $product_weight = $product->get_weight(); // get the product weight
            // Add the line item weight to the total weight calculation
            $total_weight += floatval( $product_weight * $quantity );
        }

        return $total_weight;
    }
}
