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
    ),
    'posts_per_page' => -1, // Retrieve all listings
));

// Loop through each listing with meta and status
if ($listings_with_meta_and_status->have_posts()) :
    while ($listings_with_meta_and_status->have_posts()) : $listings_with_meta_and_status->the_post();
        $property_type = get_post_meta(get_the_ID(), 'boostly_property_type', true);
        $property_url = get_post_meta(get_the_ID(), 'property_url', true);
        $is_published = get_post_status(get_the_ID()) == 'publish'; // Check if the post is published
        $boostly_pms_id = get_post_meta(get_the_ID(), 'boostly_pms_id', true);
        ?>
        <tr>
            <td><?php echo get_the_ID(); ?></td>
            <td><?php the_title(); ?></td>
            <td><?php the_author(); ?></td>
            <td><?php echo esc_html($property_type); ?></td>
            <td><?php echo esc_url($property_url); ?></td> <!-- Display Airbnb URL -->
            <td>
                <?php if ($is_published): ?>
                    <button disabled style="background-color: grey; color: white;">Published</button>
                <?php else: ?>
                    <button onclick="publishListing(<?php echo get_the_ID(); ?>);" style="background-color: blue; color: white;">Publish</button>
                <?php endif; ?>
                <button style="background-color: grey; color: white;" onclick="airbnbV2SyncAction('<?php echo esc_js($boostly_pms_id); ?>');" class="button button-primary">Sync</button>
            </td>                   
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