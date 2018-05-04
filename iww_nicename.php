<?php
/*
Plugin Name: iWantWorkwear Nicename
Plugin URI:
Description: Allows the user to make big changes.
Version: 0.3.0
Author: iWantWorkwear
Author URI: http://www.ur54.com
*/
ini_set( 'memory_limit', '1024M' );
// memory leak?
add_action( 'admin_menu', 'iww_nicename_menu', 1 );
// must call menu before iww_enqueue_scripts
add_action( 'admin_enqueue_scripts', 'iww_enqueue_scripts', 1 );

function iww_enqueue_scripts(){
  wp_enqueue_style( 'bs4css', plugins_url( 'iww_nicename/assets/bootstrap.min.css' ) );
  wp_enqueue_script( 'bs4js', plugins_url( 'iww_nicename/assets/bootstrap.min.js' ), array( 'jquery' ), '5042018', true );
}

function iww_nicename_menu(){
  add_submenu_page( 'edit.php?post_type=product', __('Product Nicenames', 'iww'), __('Nicenames', 'iww'), 'administrator', 'iww_nicename', 'nicename_menu');
}

function nicename_menu(){

  if ( !current_user_can( 'manage_product_terms' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	?>
  <div class="container-fluid">
    <?php
    $list = iww_get_data();
    nice_list( $list );
    ?>

  </div>
  <?php
}

// add_action( 'admin_post_convert_titles', 'iww_convert_titles', 1 );

function get_nice_title( $id ){
  $title = get_post_meta( $id, "_yoast_wpseo_title", true );
  if( isset( $title ) && ! empty( $title ) ){
    // if its a yoast title, remove the dynamic data stuff
     $search_for = ['%%sep%%', '%%sitename%%', 'iWantWorkwear', '&', '\'' ];
     $nicename = str_ireplace( $search_for, '', $title ); //value
   } else {
     // if there is no yoast title, grab the product title
     $nicename = get_the_title();
     // since titles are typically too large for GSF, cut it down
     if (strlen($nicename) > 60 ) {
       // wrap strings to prevent breaking words
       $nicename = wordwrap($nicename, 60);
       $nicename = substr($nicename, 0, strpos($nicename, "\n"));
     }
  }
  return trim( $nicename );
}

function nice_list( $list ){
  ?>
  <h1>Nice List: </h1>
  <table class="table table-striped table-sm">
    <thead class="thead-dark">
      <th>ID</th>
      <th>Name</th>
      <th>Description</th>
      <th>Type</th>
      <th>GTIN</th>
      <th>Age Group</th>
      <th>Gender</th>
      <th><i class="fa fa-eye"></th>
    </thead>
    <tbody>
      <?php foreach( $list as $item ) : ?>
        <tr>
          <?php foreach( $item as $attr ) : ?>
            <td>
              <?php echo $attr ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php
}

function add_to_list( $id, $name, $description, $post_type, $gtin, $age_group, $gender, $link ){
  $list = array(
    'id' => $id,
    'name' => $name,
    'desc' => $description,
    'type' => $post_type,
    'gtin' => $gtin,
    'age' => $age_group,
    'gender' => $gender,
    'link' => $link
  );
  return $list;
}

function make_link( $url ){
  return '<a href="' . $url . '">View</a>';
}

function update_products( $list ){
  $targets = array(
    'name' => 'wccaf_nice_name',
    'desc' => '_yoast_wpseo_metadesc',
    'age' => 'wccaf_age_group',
    'gender' => 'wccaf_gender'
  );
  foreach( $list as $item ){
    if( isset( $item['id'] ) ){
      $targets['gtin'] = ( $item['type'] === 'variable' ) ? 'wccaf_gtin' : 'wccaf_simple_gtin';
    }
    foreach( $targets as $key => $target ){

      if( isset( $item[$key] ) || !empty( $item[$key] ) ){
        $old = get_post_meta( $item['id'], $target, true );
        if( $item[$key] != $old ){
          update_post_meta( $item['id'], "'" . $target . "'",  "'" . $item[$key]  . "'" );
        }
      }
    }
  }
}

function iww_get_data(){
  $params = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'nopaging' => true
  );
  // nopaging is a much faster way of grabbing all the products.
  $products = new WP_Query( $params );
  if( $products->have_posts() ){
    while ( $products->have_posts() ) {
  		$products->the_post();
      $id = get_the_ID();
      $name = get_nice_title( $id );

      // I dont want to make a product just for the product type; TODO: Fix that <<
      $product = wc_get_product( $id );
      $post_type = $product->get_type();
      $age_group = get_post_meta( $id, "wccaf_age_group", true );
      $gender = get_post_meta( $id, "wccaf_gender", true );

      $description = get_post_meta( $id, "_yoast_wpseo_metadesc", true );
      if( !isset( $description ) || empty( $description ) ){
        $description = strip_tags( $product->get_short_description() );
      }

      if( 'variable' === $post_type ){
        // get children id
        $children = $product->get_children();

        foreach( $children as $id ){
          // each variation item as an individual product.
          $gtin = get_post_meta( $id, 'wccaf_gtin', true );
          $variation = wc_get_product( $id );
          $attr_str = wc_get_formatted_variation( $variation->get_variation_attributes(), true, false, true );
          $seo_name = $name . ' ' . $attr_str;
          $link = $variation->get_permalink();
          $list[$id] = add_to_list( $id, $seo_name, $description, $post_type, $gtin, $age_group, $gender, make_link($link) );
        }

      } else {
        // simple products.
        $link = $product->get_permalink();
        $gtin = get_post_meta( $id, 'wccaf_simple_gtin', true );
        $list[$id] = add_to_list( $id, $name, $description, $post_type, $gtin, $age_group, $gender, make_link($link) );
      }
  	}
    return $list;
  }
}
