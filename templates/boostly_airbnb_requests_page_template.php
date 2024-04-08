<!-- <form method="post" action="">
    <input type="text" name="sync_data" placeholder="Enter data here...">
    <button type="submit" id="sync_button" class="button">Sync</button>
</form> -->


<?php

global $wpdb;
$airbnb_request = $wpdb->prefix . "airbnb_request";

?>
    <div class="table-block" >
        <h2> Airbnb property sync </h2>
        <label id="admin_sync_notifications" style=""></label>
        <form action="" method="post">
            <table class="wp-list-table widefat fixed striped table-view-list pages">
                <thead>
                    <tr>
                        <td style="width:70%;"> Airbnb Listing URL </td>
                        <td> Action </td>
                    </tr>
                </thead>
                <tbody>
                    <tr class="request-action-row">
                        <td>
                            <input type="text" class="regular-text" name="sync_data" id="sync_data" value="" />
                        </td>
                        <td class="request-action-column">
                            <button class="button button-icon tips button-primary" id="sync_button" disabled>Sync</button>
                            <!-- <span class="spinner spinner-airbnb"></span> -->
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <form id="sync-form" action="" method="post">
            <label for="ical_sync">iCal Sync:</label>
            <select name="ical_sync" id="ical_sync">
                <option value="1">One minute</option>
                <option value="2">2 Minutes</option>
                <option value="5">5 minutes</option>
                <option value="10">10 Minutes</option>
                <option value="15">15 Minutes</option>
                <option value="30">30 Minutes</option>
            </select>

            <label for="property_sync">Property Sync:</label>
            <select name="property_sync" id="property_sync">
                <option value="30">30 Minutes</option>
                <option value="60">Once Hourly</option>
                <option value="240">4 hours</option>
                <option value="360">6 Hours</option>
                <option value="720">Twice Daily</option>
                <option value="1440">Once Daily</option>
            </select>

            <?php wp_nonce_field('boostly_airbnb_v2_nonce'); ?>

            <button type="submit" id="save_button">Save</button>
        </form>        
        <h2> Airbnb Listing Requests </h2>
                <?php
                $requests = $wpdb->get_results("SELECT * FROM $airbnb_request ORDER BY created_at DESC");

                if (!empty($requests)) {
                    $i = 1;
                    foreach ($requests as $request) {

                        $user_data  = get_user_by('email', $request->site_user);
                        $username   = $user_data->data->user_login;
                        $user_role  = $user_data->roles[0];
                        $status     = $request->request_status;

                        $airbnb_url = $request->airbnb_url;

                        $listing_id = $post_title = "";
                        if( !empty( $airbnb_url ) ) {

                            $listing_id = $wpdb->get_var("SELECT post_id from $wpdb->postmeta where meta_value = '$airbnb_url'");
                            $post_title = get_the_title( $listing_id );
                            $post_url   = get_permalink( $listing_id );

                            $post_title = "<a href='$post_url' target='_blank'>$post_title</a>";

                        }


                        // Status default labels
                        $label_class = "label-success";
                        $i++;
                    }
                } else {
                    echo '<tr><td style="text-align: center;" colspan="5">No Request</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>Property Type</th>
                <th>Airbnb URL</th> <!-- New column for Airbnb URL -->
                <!-- Add more columns as needed -->
            </tr>
        </thead>
        <tbody id="boostly_airbnb_v2">
            <?php
            // Query to fetch listings with specified meta and database fields
            $listings_with_meta_and_status = new WP_Query(array(
                'post_type' => 'listing',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'boostly_property_type',
                        'compare' => 'EXISTS', // Check if meta key exists
                    ),
                    // array(
                    //     'key' => 'property_url',
                    //     'compare' => 'EXISTS', // Check if meta key exists
                    // ),
                    // array(
                    //     'key' => 'boostly_airbnb_host_email',
                    //     'compare' => 'EXISTS', // Check if meta key exists
                    // ),
                ),
                'posts_per_page' => -1, // Retrieve all listings
            ));

            // Loop through each listing with meta and status
            if ($listings_with_meta_and_status->have_posts()) :
                while ($listings_with_meta_and_status->have_posts()) : $listings_with_meta_and_status->the_post();
                    $property_type = get_post_meta(get_the_ID(), 'boostly_property_type', true);
                    $property_url = get_post_meta(get_the_ID(), 'property_url', true);
            ?>
                    <tr>
                        <td><?php echo get_the_ID(); ?></td>
                        <td><?php the_title(); ?></td>
                        <td><?php the_author(); ?></td>
                        <td><?php echo esc_html($property_type); ?></td>
                        <td><?php echo esc_url($property_url); ?></td> <!-- Display Airbnb URL -->
                        <td><?php echo esc_html($host_email); ?></td> <!-- Display Host -->
                        <!-- Add more cells as needed -->
                    </tr>
            <?php
                endwhile;
            else :
                ?>
                <tr>
                    <td colspan="7">No listings with specified meta and status found.</td>
                </tr>
            <?php
            endif;
            wp_reset_postdata(); // Reset post data
            ?>
        </tbody>
    </table>        
    </div>
<?php
