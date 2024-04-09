<?php
/*
Plugin Name: Boostly AirBNB Scraper V2
Description: Adds a submenu "AirBnb Requests V2" below the custom post type "Listings".
Version: 1.0.0
Author: ~ Boostly
*/

define('AIRBNB_CLOUDWAYS_URL', 'https://phpstack-1244200-4452379.cloudwaysapps.com/');

if (!wp_next_scheduled('boostly_custom_daily_event')) {
    wp_schedule_event(time(), 'daily', 'boostly_custom_daily_event');
}

// Function to create the submenu page
function boostly_add_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=listing',   // Parent menu slug
        'AirBnb Requests V2',             // Page title
        'AirBnb Requests V2',             // Menu title
        'manage_options',                 // Capability
        'boostly-airbnb-requests',       // Menu slug
        'boostly_airbnb_requests_page'    // Callback function
    );
}

// Callback function to display the submenu page
function boostly_airbnb_requests_page() {
    // Load the template file
    include_once(plugin_dir_path(__FILE__) . 'templates/boostly_loading_screen.php');
    include_once(plugin_dir_path(__FILE__) . 'templates/boostly_airbnb_requests_page_template.php');
}

// Hook the function to the admin_menu action
add_action('admin_menu', 'boostly_add_submenu_page');


// Enqueue the JavaScript file
function boostly_enqueue_scripts() {
    // Register the script
    wp_register_script('boostly-airbnbv2', plugin_dir_url(__FILE__) . 'assets/js/airbnbv2.js', array('jquery'), '1.0', true);
    // Enqueue the script
    wp_enqueue_script('boostly-airbnbv2');
    // Localize the script with the AJAX URL
    wp_localize_script('boostly-airbnbv2', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'boostly_airbnb_v2_ical_sync_setting' => get_option('boostly_airbnb_v2_ical_sync_setting', '') ?? '',
        'boostly_airbnb_v2_property_sync_setting' => get_option('boostly_airbnb_v2_property_sync_setting', '') ?? ''
    ));
}

add_action('admin_enqueue_scripts', 'boostly_enqueue_scripts');


function boostly_curl_request_cloudways_aribnb($endpoint){

    $boostly_currency = "USD";

    $baseUrl = AIRBNB_CLOUDWAYS_URL . $endpoint;

    $params = [
        'currency' => $boostly_currency,
    ];
    
    $url = $baseUrl . '?' . http_build_query($params);

    // $url = AIRBNB_CLOUDWAYS_URL . $endpoint;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
    //   CURLOPT_URL => AIRBNB_CLOUDWAYS_URL . $endpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwbXMiOiJhaXJibmIiLCJ1c2VyIjoiZGV2QGJvb3N0bHkuY29tIiwicGFzc3dvcmQiOiIjVGVhbUJvb3N0bHlAMy4wISEiLCJpYXQiOjE3MDQ3NzQwODd9.SP6VeUPVG80kNPZFvCc5MCqysGbC9e3s-RZoDkw-buQ'
      ),
    ));
    
    $response = curl_exec($curl);

    $json_response = json_decode($response, true);
    
    curl_close($curl);

    return $json_response;
}

function boostly_sync_all_listings(){

    // Arguments for the query
    $args = array(
        'post_type'      => 'listing', // Custom post type 'listing'
        'posts_per_page' => -1,        // Get all posts
        'meta_query'     => array(
            array(
                'key'   => 'boostly_property_type', // The meta key
                'value' => 'airbnb',              // The value to check against
            ),
        ),
    );

    // The Query
    $the_query = new WP_Query($args);

    // The Loop
    if ($the_query->have_posts()) {
        while ($the_query->have_posts()) {
            $the_query->the_post();
            // Print the ID of each post

            $property_id = get_post_meta(get_the_ID(), 'boostly_listing_id', true);
            if(!empty($property_id)){
                $endpoint = 'boostly_airbnb_scraper/' . $property_id;
                $airbnb_data = boostly_curl_request_cloudways_aribnb($endpoint);
                $airbnb_data['property_id'] = $property_id;
                boostly_create_update_listing_data(get_the_ID(), $airbnb_data); 
            }           
            // $endpoint = 'boostly_airbnb_scraper/' . $property_id;
            sleep(2);
        }
    } else {
        // no posts found
        echo 'No posts found';
    }

    // Restore original Post Data
    wp_reset_postdata();

    exit();
}

function boostly_reload_list_template(){
    include_once(plugin_dir_path(__FILE__) . 'templates/boostly_airbnb_request_body_template.php');
}

// AJAX action to handle the sync request
function boostly_sync_action() {

    // Handle the AJAX request here
    $data = $_POST['data'];

    $property_id = $data;
    $boostly_currency = "USD";
    // $endpoint = 'boostly_airbnb_scraper/' . $property_id . "?currency=" . $boostly_currency;
    $endpoint = 'boostly_airbnb_scraper/' . $property_id;
    $airbnb_data = boostly_curl_request_cloudways_aribnb($endpoint);
    $airbnb_data['property_id'] = $property_id;

    boostly_create_update_listing_data(0, $airbnb_data);

    wp_send_json_success(array(
        'message' => 'Success',
        'status' => true,
        'endpoint' => $endpoint,
        'data' => $airbnb_data,
        'property_id' => $property_id
    ));    

    wp_die(); // Always include this to terminate the AJAX request properly
}

function boostly_create_update_listing_data($post_id, $airbnb_data){

    $args = array(
        'post_type'      => 'listing', // Custom post type
        'posts_per_page' => -1, // Retrieve all posts
        'meta_query'     => array(
            array(
                'key'   => 'boostly_listing_id',
                'value' => $airbnb_data['property_id'], // Value to check
            ),
        ),
    );    

    $listing_query = new WP_Query( $args );

    $post_description = $airbnb_data['description'] ? $airbnb_data['description'] : $airbnb_data['shortDescription'];

    if ( $listing_query->have_posts() ) {
        $listing_query->the_post();
        $post_id = get_the_ID(); // Get the post ID    

        $post_data = array(
            'ID' => $post_id, // Specify the ID of the post you want to update
            'post_title' => isset($airbnb_data['title']) ? $airbnb_data['title'] : '',
            'post_type' => 'listing',
            'post_content' => $airbnb_data['shortDescription']
        );
        
        // Update the post
        $update_post_result = wp_update_post($post_data);        
    } else {
        // No posts found with the specified meta key and value
        $post_data = array(
            'post_title' => isset($airbnb_data['title']) ? $airbnb_data['title'] : '',
            'post_status' => 'draft',
            'post_type' => 'listing',
            'post_content' => $airbnb_data['shortDescription']
         );
      
         $post_id = wp_insert_post($post_data);        
    }    

    $guests = explode(' ', $airbnb_data['guests'][0] ?? 1); //boostly_guests
    update_post_meta($post_id, 'boostly_guests', $guests[0]);

    $bedrooms = null;
    $beds = null;
    $baths = null;
    
    foreach ($airbnb_data as $key => $value) {
        if (preg_match('/(\d+)/', $value, $matches)) {
            switch ($key) {
                case 'bedrooms':
                    $bedrooms = $matches[0];
                    update_post_meta($post_id, 'boostly_listing_bedrooms', $bedrooms);
                    break;
                case 'beds':
                    $beds = $matches[0];
                    update_post_meta($post_id, 'boostly_beds', $beds);
                    break;
                case 'baths':
                    $baths = $matches[0];
                    update_post_meta($post_id, 'boostly_baths', $baths);
                    break;
                default:
                    // Handle unexpected keys
                    break;
            }
        }
    }    

    $listingAmenities = $airbnb_data["amenities"];

    if (!empty($listingAmenities)) {
        foreach ($listingAmenities as $amenities) {
            boostly_airbnb_set_property_category($amenities, $post_id, 'listing_amenity');
        }
    }    

    $roomType = $airbnb_data['roomType'];
    
    if (!empty($roomType)) {
        boostly_airbnb_set_property_category($roomType, $post_id, 'listing_type');
        boostly_airbnb_set_property_category($roomType, $post_id, 'room_type');
    }    

    boostly_airbnb_update_property_calendar_v3($post_id, $airbnb_data);


    update_post_meta($post_id, 'property_url' , 'airbnb.com/rooms/' . $airbnb_data['property_id']);
     update_post_meta($post_id, 'boostly_show_map', 1);
     update_post_meta($post_id, 'boostly_zip', ''); // default
     update_post_meta($post_id, 'boostly_night_price', $airbnb_data['price']['amount'] ?? 0); // default
     update_post_meta($post_id, 'boostly_listing_address', isset($airbnb_data['address']) ? $airbnb_data['address'] : '');
     update_post_meta($post_id, 'boostly_geolocation_long', $airbnb_data['coordinates']['lng'] ?? 0);
     update_post_meta($post_id, 'boostly_geolocation_lat', $airbnb_data['coordinates']['lat'] ?? 0);
     update_post_meta($post_id, 'boostly_listing_location', 0 . ',' . 0 . ',9');
     update_post_meta($post_id, 'boostly_featured', 0);
     update_post_meta($post_id, 'boostly_pets', 0);
  
     // update sku
     update_post_meta($post_id, 'boostly_listing_id', $airbnb_data['property_id']);
     update_post_meta($post_id, 'boostly_property_type', 'airbnb');
     update_post_meta($post_id, '_photos', json_encode(isset($airbnb_data['photos']) ? $airbnb_data['photos'] : ''));
  
     $parent_id = 0;
        //country
        if (!empty($country)) {
        $parent_id = boostly_airbnb_set_property_category($country, $post_id, 'listing_country');

        if (!empty($city)) {
            boostly_airbnb_set_property_category($city, $post_id, 'listing_country', $parent_id);
        }
    }

    upload_images_in_sort_order_v3($post_id, $airbnb_data['photos']);    

     $calendar = isset($airbnb_data['calendar']) ? $airbnb_data['calendar'] : array();
     $photo = isset($airbnb_data['photos']) ? $airbnb_data['photos'] : array();
  
     if ( !empty( $photos ) ) {
  
        $i = 0;
        $pms_images_sync_limit      = boostly_pms_option('boostly_airbnb_images_sync_limit');
        $boostly_images_sync_limit  = boostly_pms_option('boostly_listing_images_sync_limit');
        $images_sync_limit          = !empty( $pms_images_sync_limit ) ? $pms_images_sync_limit : $boostly_images_sync_limit;
  
        foreach ($photos as $photo) {
            $imgArr = explode('?', $photo['pictureUrl']);
            if ($i == 0) {
                $feature_id = boostly_airbnb_upload_media($imgArr[0]);
                update_post_meta($post_id, '_thumbnail_id', $feature_id);
            } else {
                $gallery_id = boostly_airbnb_upload_media($imgArr[0]);
                add_post_meta($post_id, 'boostly_listing_images', $gallery_id);
            }
  
            if( !empty( $images_sync_limit ) && $images_sync_limit == $i ) break; 
  
            $i++;
        }
  
    }
  
    update_post_meta($post_id, 'boostly_pms_id', $airbnb_data['property_id']); //editbenjo

}

function boostly_airbnb_update_property_calendar_v3($post_id, $property_data) {
    
    $calendar = $property_data['calendar'];

    if (!empty($calendar)) {
        $available_dates = array();
        $not_available_dates = array();
        $reservations = array();

        foreach ($calendar as $cal) {
            $available = $cal['available'];
            $date = $cal['date'];
            if ($available == false) {
                $not_available_dates[] = $date;
                //_available_dates

                $modify_date = date("d-m-Y", strtotime($date));
                $timestamp = strtotime($modify_date);
                $reservations[$timestamp] = 1;
            } else {
                $available_dates[] = $date;
                //_not_available_dates
            }
        }

        update_post_meta($post_id, '_available_dates', json_encode(array_filter(array_values(array_unique($available_dates)))));
        update_post_meta($post_id, '_not_available_dates', json_encode(array_filter(array_values(array_unique($not_available_dates)))));

        update_post_meta($post_id, 'reservation_unavailable', $reservations);
        update_post_meta($post_id, '_availabilities', json_encode($calendar));
    }

}

function upload_images_in_sort_order_v3($post_id, $data){
   $pictures = (!empty($data)) ? $data: '';

   // Re index by Benjo
   $pictures = array_values($pictures);

   $saved_images = get_post_meta($post_id, 'boostly_listing_images');
   $sorted_images = array();

   if($pictures){
       $gallery_id = boostly_upload_media_v3($pictures[0], '.jpg', "");
       update_post_meta($post_id, '_thumbnail_id', $gallery_id);
   }   

   $total_loop = count($pictures) > 50 ? 50 : count($pictures);
   
   for($i = 1; $i < $total_loop; $i++){
       // $pictures[$i]["displayOrder"] = $i;
       $f_name = end(explode("/", $pictures[$i]));

       $original = $pictures[$i];
       $gallery_id = boostly_upload_media_v3($original, '.jpg', $caption);
       array_push($sorted_images, $gallery_id);
   }

   if(!empty($saved_images)){
       foreach ($saved_images as $image){
           // wp_delete_attachment($image, true);
           delete_post_meta($post_id, "boostly_listing_images", $image);
       }
   }    

   foreach($sorted_images as $image){
       add_post_meta($post_id, "boostly_listing_images", $image);
   }         
}

//Custom  Benjo
function boostly_upload_media_v3($url, $extention = '', $caption = '')
{
  $upload_dir = wp_upload_dir();
  $image_data = file_get_contents($url);
  $filename   = basename($url) . $extention;

  // clean filename to save resources and not upload anything.
  $filename = clean_str_fname_v3($filename);

  $file       = (wp_mkdir_p($upload_dir['path'])) ? $upload_dir['path'] . '/' . $filename : $upload_dir['basedir'] . '/' . $filename;

  file_put_contents($file, $image_data);

  $wp_filetype = wp_check_filetype($filename, null);
  $attachment  = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title'     => sanitize_file_name($filename),
      'post_content'   => '',
  //  'post_excerpt'   => $caption,
      'post_status'    => 'inherit'
  );

  if(!empty($caption)){
  $attachment["post_excerpt"] = $caption;
  }

  // if exists already fetch and send attachment ID
  if (post_exists(sanitize_file_name($filename))) {
      $attachment = get_page_by_title(sanitize_file_name($filename), OBJECT, 'attachment');
      if (!empty($attachment)) {
          return $attachment->ID;
      }
  }     

   $attach_id = wp_insert_attachment($attachment, $file);

   require_once(ABSPATH . 'wp-admin/includes/image.php');

   $attach_data = wp_generate_attachment_metadata($attach_id, $file);

   wp_update_attachment_metadata($attach_id, $attach_data);
   return $attach_id;
}
// Custom  Benjo
// Clean string so attachment exist can work efficiently
function clean_str_fname_v3($string) {
   $string = str_replace(' ', '-', $string);
   $string = str_replace('-', '', $string);
   $string = str_replace('.jpg', '', $string);
   $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
   $string = $string . ".jpg";

   return $string; // Removes special chars.
}

add_action('wp_ajax_boostly_sync_action', 'boostly_sync_action');


add_action('wp_ajax_boostly_reload_list_template', 'boostly_reload_list_template');

add_action('wp_ajax_boostly_airbnb_v2_save_sync_options', 'boostly_airbnb_v2_save_sync_options');

function boostly_airbnb_v2_save_sync_options() {

    $ical_sync_minutes = isset($_POST['ical_sync']) ? intval($_POST['ical_sync']) : 0;
    $property_sync_minutes = isset($_POST['property_sync']) ? intval($_POST['property_sync']) : 0;

    // Convert the values to seconds
    $ical_sync_seconds = $ical_sync_minutes * 60; // Convert minutes to seconds
    $property_sync_seconds = $property_sync_minutes * 60; // Convert minutes to seconds

    // Save the settings
    update_option('boostly_airbnb_v2_ical_sync_setting', $ical_sync_seconds);
    update_option('boostly_airbnb_v2_property_sync_setting', $property_sync_seconds);

    boostly_airbnb_v2_reschedule_events();

    // Return a success response
    echo 'Options saved';
    wp_die(); // this is required to terminate immediately and return a proper response
}


// Below is all cron job events

register_deactivation_hook(__FILE__, 'boostly_airbnb_v2_deactivate');

function boostly_airbnb_v2_deactivate() {
    $timestamp = wp_next_scheduled('boostly_airbnb_v2_sync_all_listings_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boostly_airbnb_v2_sync_all_listings_event');
    }

    $timestamp = wp_next_scheduled('boostly_airbnb_v2_update_property_calendar_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boostly_airbnb_v2_update_property_calendar_event');
    }
}


add_filter('cron_schedules', 'boostly_airbnb_v2_add_cron_interval');

function boostly_airbnb_v2_add_cron_interval($schedules) {
    $property_sync_seconds = get_option('boostly_airbnb_v2_property_sync_setting', 1440 * 60);
    $ical_sync_seconds = get_option('boostly_airbnb_v2_ical_sync_setting', 60 * 60);

    // Adds once weekly to the existing schedules.
    $schedules['boostly_airbnb_v2_property_sync_interval'] = array(
        'interval' => $property_sync_seconds,
        'display' => __('Boostly Property Sync Interval', 'textdomain')
    );

    $schedules['boostly_airbnb_v2_ical_sync_interval'] = array(
        'interval' => $ical_sync_seconds,
        'display' => __('Boostly iCal Sync Interval', 'textdomain')
    );

    return $schedules;
}

function boostly_airbnb_v2_reschedule_events() {
    $property_sync_seconds = get_option('boostly_airbnb_v2_property_sync_setting', 1440 * 60);
    $ical_sync_seconds = get_option('boostly_airbnb_v2_ical_sync_setting', 60 * 60);

    // Clear the old scheduled hooks, if any
    $timestamp = wp_next_scheduled('boostly_airbnb_v2_sync_all_listings_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boostly_airbnb_v2_sync_all_listings_event');
    }

    $timestamp = wp_next_scheduled('boostly_airbnb_v2_update_property_calendar_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'boostly_airbnb_v2_update_property_calendar_event');
    }

    // Schedule new hooks with the updated interval
    wp_schedule_event(time(), 'boostly_airbnb_v2_property_sync_interval', 'boostly_airbnb_v2_sync_all_listings_event');
    wp_schedule_event(time(), 'boostly_airbnb_v2_ical_sync_interval', 'boostly_airbnb_v2_update_property_calendar_event');
}

// Hook into your option update
add_action('update_option_boostly_airbnb_v2_property_sync_setting', 'boostly_airbnb_v2_reschedule_events');
add_action('update_option_boostly_airbnb_v2_ical_sync_setting', 'boostly_airbnb_v2_reschedule_events');

add_action('boostly_airbnb_v2_sync_all_listings_event', 'boostly_sync_all_listings');
add_action('boostly_airbnb_v2_update_property_calendar_event', 'boostly_airbnb_update_property_calendar_v3');

