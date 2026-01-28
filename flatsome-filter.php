<?php
/**
 * Plugin Name: FLATSOME FILTER
 * Plugin URI: https://ledaitu.com
 * Description: Bộ lọc sản phẩm chuyên nghiệp dành riêng cho theme Flatsome, hỗ trợ lọc theo giá và thuộc tính tùy chỉnh thuộc flatsome.
 * Version: 1.0.1
 * Author: Lê Đại Tú
 * Text Domain: flatsome-filter
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Flatsome_Filter
 */
class Flatsome_Filter {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Khởi tạo các module
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        // Sẽ bao gồm các file module admin và frontend sau
    }

    private function init_hooks() {
        // Hooks cơ bản
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );

        // Frontend Hooks
        add_action( 'woocommerce_before_shop_loop', array( $this, 'replace_default_filters' ), 10 );
        add_action( 'pre_get_posts', array( $this, 'handle_filtering' ) );

        // AJAX Wrappers (Priority > 10 to ensure Form is outside)
        add_action( 'woocommerce_before_shop_loop', array( $this, 'open_ajax_wrapper' ), 20 );
        add_action( 'woocommerce_after_shop_loop', array( $this, 'close_ajax_wrapper' ), 30 );

        // Always push out of stock to bottom
        add_filter( 'posts_clauses', array( $this, 'push_out_of_stock_to_bottom' ), 20, 2 );
    }

    /**
     * Nạp ngôn ngữ cho plugin
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'flatsome-filter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function replace_default_filters() {
        // Ẩn các bộ lọc và sắp xếp mặc định của WooCommerce
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        
        // Ẩn các thành phần riêng của theme Flatsome (nếu có)
        remove_action( 'woocommerce_before_shop_loop', 'flatsome_products_filter_button', 15 );
        remove_action( 'woocommerce_before_shop_loop', 'flatsome_products_filter_button', 20 );
        
        // Render bộ lọc tùy chỉnh của chúng ta
        $this->render_filter_ui();
    }

    public function render_filter_ui() {
        $enabled_attrs = get_option( 'ff_enabled_attributes', array() );
        $show_price = get_option( 'ff_show_price_filter', '1' );
        
        // Lấy số lượng sản phẩm hiện tại
        global $wp_query;
        $total_results = $wp_query->found_posts;

        echo '<div class="ff-filter-container">';
        echo '<div class="ff-overlay"></div>'; // Thêm lớp phủ mờ nền cho mobile
        
        // Nút trigger cho mobile (Floating)
        echo '<div class="ff-mobile-floating-btn">';
        echo '<div class="ff-floating-icon">';
        include plugin_dir_path( __FILE__ ) . 'assets/icon/filter.svg';
        echo '</div>';
        echo '<span>' . __( 'Filter & Sort', 'flatsome-filter' ) . '</span>';
        echo '</div>';

        echo '<div class="ff-filter-icon-desktop">';
        include plugin_dir_path( __FILE__ ) . 'assets/icon/filter.svg';
        echo '<span>' . __( 'Bộ lọc', 'flatsome-filter' ) . '</span>';
        echo '</div>';
        
        echo '<form id="ff-filter-form" method="get" action="' . esc_url( strtok( $_SERVER["REQUEST_URI"], '?' ) ) . '" class="ff-filter-form">';
        
        // Wrapper cho Mobile Modal (Trên desktop sẽ là display: contents)
        echo '<div class="ff-filter-form-inner">';
        
        echo '<div class="ff-mobile-sheet-header">';
        echo '<a href="#" class="ff-reset-btn-sheet">' . __( 'Reset', 'flatsome-filter' ) . '</a>';
        echo '<span class="ff-sheet-title">' . __( 'Filters', 'flatsome-filter' ) . '</span>';
        echo '<i class="fa fa-times ff-close-sheet"></i>';
        echo '</div>';
        
        echo '<div class="ff-sheet-body">';
        
        // Giữ lại các tham số cần thiết khác (như trang, tìm kiếm)
        if ( is_search() ) {
            echo '<input type="hidden" name="s" value="' . esc_attr( get_search_query() ) . '">';
            echo '<input type="hidden" name="post_type" value="product">';
        }

        // 0. Sorting (Order By)
        $orderby = isset( $_GET['orderby'] ) ? esc_attr( $_GET['orderby'] ) : 'popularity';
        $sort_options = array(
            'popularity' => __( 'Phổ biến', 'flatsome-filter' ),
            'date'       => __( 'Mới nhất', 'flatsome-filter' ),
            'price'      => __( 'Giá thấp -> cao', 'flatsome-filter' ),
            'price-desc' => __( 'Giá cao -> thấp', 'flatsome-filter' ),
        );

        echo '<div class="ff-filter-group ff-dropdown" data-name="orderby">';
        echo '<label class="ff-group-label">' . __( 'Sắp xếp', 'flatsome-filter' ) . '</label>';
        echo '<div class="ff-dropdown-label"><span>' . esc_html( $sort_options[$orderby] ?? $sort_options['popularity'] ) . '</span> <i class="fa fa-angle-down"></i></div>';
        echo '<div class="ff-dropdown-content">';
        echo '<ul class="ff-dropdown-list ff-sort-list">';
        foreach ( $sort_options as $id => $name ) {
            $active = ( $orderby === $id ) ? 'is-active' : '';
            echo '<li class="' . $active . '" data-value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '">';
        echo '</div>';

        // 1. Price Filter
        if ( $show_price == '1' ) {
            $price_ranges_str = get_option( 'ff_price_ranges', '' );
            if ( $price_ranges_str ) {
                $ranges = explode( "\n", str_replace( "\r", "", $price_ranges_str ) );
                $selected_range = isset( $_GET['price_range'] ) ? esc_attr( $_GET['price_range'] ) : '';
                
                $current_label = __( 'Chọn khoảng giá', 'flatsome-filter' );
                $list_items = '';
                
                foreach ( $ranges as $range_line ) {
                    if ( empty( trim( $range_line ) ) ) continue;
                    list( $val, $label ) = array_pad( explode( '|', $range_line ), 2, '' );
                    $val = trim( $val );
                    $label = trim( $label );
                    
                    if ( ! $label ) {
                        list( $min, $max ) = array_pad( explode( '-', $val ), 2, '' );
                        $min = intval( trim( $min ) );
                        $max = intval( trim( $max ) );
                        if ( $min > 0 && $max > 0 ) $label = strip_tags( wc_price( $min ) ) . ' - ' . strip_tags( wc_price( $max ) );
                        elseif ( $min > 0 ) $label = __( 'Từ ', 'flatsome-filter' ) . strip_tags( wc_price( $min ) );
                        elseif ( $max > 0 ) $label = __( 'Dưới ', 'flatsome-filter' ) . strip_tags( wc_price( $max ) );
                        else $label = $val;
                    }

                    $active = ( $selected_range === $val ) ? 'is-active' : '';
                    if ( $active ) $current_label = $label;
                    $list_items .= '<li class="' . $active . '" data-value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</li>';
                }

                $active_group_class = ! empty( $selected_range ) ? 'ff-active' : '';
                echo '<div class="ff-filter-group ff-dropdown ' . $active_group_class . '" data-name="price_range">';
                echo '<label class="ff-group-label">' . __( 'Khoảng giá', 'flatsome-filter' ) . '</label>';
                echo '<div class="ff-dropdown-label"><span>' . esc_html( $current_label ) . '</span> <i class="fa fa-angle-down"></i></div>';
                echo '<div class="ff-dropdown-content">';
                echo '<ul class="ff-dropdown-list">' . $list_items . '</ul>';
                echo '</div>';
                echo '<input type="hidden" name="price_range" value="' . esc_attr( $selected_range ) . '">';
                echo '</div>';
            }
        }

        // 2. Attribute Filters
        foreach ( $enabled_attrs as $attr_name ) {
            $taxonomy = 'pa_' . $attr_name;
            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $label = wc_attribute_label( $taxonomy );
                $is_color = ( stripos( $taxonomy, 'color' ) !== false || stripos( $taxonomy, 'mau' ) !== false );
                
                if ( $is_color ) {
                    $selected_colors = isset( $_GET['ux_color'] ) ? (array) $_GET['ux_color'] : array();
                    
                    $active_group_class = ! empty( $selected_colors ) ? 'ff-active' : '';
                    echo '<div class="ff-filter-group ff-dropdown ff-swatch-group ' . $active_group_class . '">';
                    echo '<label class="ff-group-label">' . esc_html( strtoupper( $label ) ) . '</label>';
                    echo '<div class="ff-dropdown-label"><span>' . esc_html( $label ) . '</span> <i class="fa fa-angle-down"></i></div>';
                    echo '<div class="ff-dropdown-content ff-swatches-content">';
                    echo '<div class="ff-swatches">';
                    foreach ( $terms as $term ) {
                        $ux_color = get_term_meta( $term->term_id, 'ux_color', true );
                        $is_checked = in_array( $term->slug, $selected_colors );
                        $active_class = $is_checked ? 'is-active' : '';
                        $style = $ux_color ? 'style="background-color:' . esc_attr( $ux_color ) . '"' : '';
                        
                        echo '<div class="ff-swatch-item">';
                        echo '<input type="checkbox" name="ux_color[]" value="' . esc_attr( $term->slug ) . '" id="ff-color-' . esc_attr( $term->term_id ) . '" ' . checked( $is_checked, true, false ) . ' style="display:none;">';
                        echo '<label for="ff-color-' . esc_attr( $term->term_id ) . '" class="ff-swatch ' . $active_class . '" ' . $style . ' title="' . esc_attr( $term->name ) . '"></label>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    $selected_val = isset( $_GET['filter_' . $attr_name] ) ? esc_attr( $_GET['filter_' . $attr_name] ) : '';
                    $current_label = $label;
                    $list_items = '';

                    foreach ( $terms as $term ) {
                        $active = ( $selected_val === $term->slug ) ? 'is-active' : '';
                        if ( $active ) $current_label = $term->name;
                        $list_items .= '<li class="' . $active . '" data-value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</li>';
                    }

                    $active_group_class = ! empty( $selected_val ) ? 'ff-active' : '';
                    echo '<div class="ff-filter-group ff-dropdown ' . $active_group_class . '" data-name="filter_' . esc_attr( $attr_name ) . '">';
                    echo '<label class="ff-group-label">' . esc_html( strtoupper( $label ) ) . '</label>';
                    echo '<div class="ff-dropdown-label"><span>' . esc_html( $current_label ) . '</span> <i class="fa fa-angle-down"></i></div>';
                    echo '<div class="ff-dropdown-content">';
                    echo '<ul class="ff-dropdown-list ff-chip-list">' . $list_items . '</ul>';
                    echo '</div>';
                    echo '<input type="hidden" name="filter_' . esc_attr( $attr_name ) . '" value="' . esc_attr( $selected_val ) . '">';
                    echo '<input type="hidden" name="query_type_' . esc_attr( $attr_name ) . '" value="and">';
                    echo '</div>';
                }
            }
        }
        
        echo '</div>'; // .ff-sheet-body
        
        echo '<div class="ff-mobile-sheet-footer">';
        echo '<button type="button" class="ff-apply-btn ff-close-sheet">' . sprintf( __( '%d kết quả', 'flatsome-filter' ), $total_results ) . '</button>';
        echo '</div>';
        
        echo '</div>'; // .ff-filter-form-inner

        echo '<div class="ff-actions-desktop">';
        $reset_url = strtok( $_SERVER["REQUEST_URI"], '?' );
        if ( is_search() ) {
            $reset_url = add_query_arg( array( 's' => get_search_query(), 'post_type' => 'product' ), $reset_url );
        }
        echo '<a href="' . esc_url( $reset_url ) . '" class="ff-reset-btn">' . __( 'Xóa bộ lọc', 'flatsome-filter' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    public function handle_filtering( $query ) {
        if ( is_admin() || ! $query->is_main_query() || ! ( is_shop() || is_product_category() || is_search() ) ) {
            return;
        }

        // 1. Lọc theo thuộc tính (Taxonomy Query)
        $tax_query = $query->get( 'tax_query' );
        if ( ! is_array( $tax_query ) ) {
            $tax_query = array();
        }

        $attr_query = array( 'relation' => 'AND' );
        $has_attr_filter = false;

        $enabled_attrs = get_option( 'ff_enabled_attributes', array() );
        foreach ( $enabled_attrs as $attr_name ) {
            $taxonomy = 'pa_' . $attr_name;
            $is_color = ( stripos( $taxonomy, 'color' ) !== false || stripos( $taxonomy, 'mau' ) !== false );
            
            // Xử lý Màu sắc (ux_color)
            if ( $is_color && ! empty( $_GET['ux_color'] ) ) {
                $attr_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => (array) $_GET['ux_color'],
                    'operator' => 'IN'
                );
                $has_attr_filter = true;
            } else {
                // Xử lý các thuộc tính khác
                $param = 'filter_' . $attr_name;
                if ( ! empty( $_GET[$param] ) ) {
                    $attr_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => explode( ',', $_GET[$param] ),
                        'operator' => 'IN'
                    );
                    $has_attr_filter = true;
                }
            }
        }

        if ( $has_attr_filter ) {
            $tax_query[] = $attr_query;
            $query->set( 'tax_query', $tax_query );
        }

        // 2. Lọc theo giá (Meta Query)
        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        if ( ! empty( $_GET['price_range'] ) ) {
            $range_val = esc_attr( $_GET['price_range'] );
            list( $min, $max ) = array_pad( explode( '-', $range_val ), 2, '' );
            
            $min_val = (int)$min;
            $max_val = (int)$max;

            if ( $min !== '' || ($max !== '' && $max_val > 0) ) {
                $price_cond = array(
                    'key'     => '_price',
                    'type'    => 'NUMERIC',
                );

                if ( $min !== '' && $max !== '' && $max_val > 0 ) {
                    $price_cond['value']   = array( $min_val, $max_val );
                    $price_cond['compare'] = 'BETWEEN';
                } elseif ( $min !== '' ) {
                    $price_cond['value']   = $min_val;
                    $price_cond['compare'] = '>=';
                } elseif ( $max !== '' && $max_val > 0 ) {
                    $price_cond['value']   = $max_val;
                    $price_cond['compare'] = '<=';
                }

                if ( isset( $price_cond['compare'] ) ) {
                    $meta_query[] = $price_cond;
                    $query->set( 'meta_query', $meta_query );
                }
            }
        }

        // 3. Sắp xếp (OrderBy)
        if ( ! empty( $_GET['orderby'] ) ) {
            $orderby = esc_attr( $_GET['orderby'] );
            switch ( $orderby ) {
                case 'price':
                    $query->set( 'orderby', 'meta_value_num' );
                    $query->set( 'meta_key', '_price' );
                    $query->set( 'order', 'ASC' );
                    break;
                case 'price-desc':
                    $query->set( 'orderby', 'meta_value_num' );
                    $query->set( 'meta_key', '_price' );
                    $query->set( 'order', 'DESC' );
                    break;
                case 'date':
                    $query->set( 'orderby', 'date' );
                    $query->set( 'order', 'DESC' );
                    break;
                case 'popularity':
                    $query->set( 'orderby', 'meta_value_num' );
                    $query->set( 'meta_key', 'total_sales' );
                    $query->set( 'order', 'DESC' );
                    break;
            }
        }
    }


    /**
     * Đẩy các sản phẩm "outofstock" xuống cuối danh sách.
     * WooCommerce lưu trữ: 'instock', 'outofstock', 'onbackorder'.
     * Thứ tự ASC sẽ đưa 'instock' lên trước 'outofstock'.
     */
    public function push_out_of_stock_to_bottom( $clauses, $query ) {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) && ! ( $query->is_search() && 'product' === $query->get( 'post_type' ) ) ) {
            return $clauses;
        }

        global $wpdb;

        // Tránh trùng lặp join nếu đã có join meta_key này
        if ( strpos( $clauses['join'], 'ff_stock_status' ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} ff_stock_status ON ({$wpdb->posts}.ID = ff_stock_status.post_id AND ff_stock_status.meta_key = '_stock_status')";
        }

        $clauses['orderby'] = "ff_stock_status.meta_value ASC, " . $clauses['orderby'];

        return $clauses;
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Flatsome Filter',
            'Flatsome Filter',
            'manage_options',
            'flatsome-filter',
            array( $this, 'admin_settings_page' )
        );
    }

    public function add_action_links( $links ) {
        $settings_link = '<a href="admin.php?page=flatsome-filter">' . __( 'Settings', 'flatsome-filter' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function enqueue_assets() {
        if ( is_admin() || ! ( is_shop() || is_product_category() || is_search() ) ) {
            return;
        }
        wp_enqueue_style( 'ff-filter-style', plugin_dir_url( __FILE__ ) . 'assets/css/filter.css', array(), '1.0.0' );
        wp_enqueue_script( 'ff-filter-script', plugin_dir_url( __FILE__ ) . 'assets/js/filter.js', array( 'jquery' ), '1.0.0', true );
    }

    public function open_ajax_wrapper() {
        echo '<div class="products-container" id="ff-ajax-container">';
    }

    public function close_ajax_wrapper() {
        echo '</div>'; // .products-container
    }


    public function admin_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Xử lý lưu cài đặt
        if ( isset( $_POST['ff_save_settings'] ) && check_admin_referer( 'ff_settings_nonce' ) ) {
            $enabled_attrs = isset( $_POST['enabled_attributes'] ) ? array_map( 'sanitize_text_field', $_POST['enabled_attributes'] ) : array();
            update_option( 'ff_enabled_attributes', $enabled_attrs );
            update_option( 'ff_show_price_filter', isset( $_POST['show_price_filter'] ) ? '1' : '0' );
            update_option( 'ff_price_ranges', sanitize_textarea_field( $_POST['ff_price_ranges'] ) );
            echo '<div class="updated"><p>Cài đặt đã được lưu.</p></div>';
        }

        $all_attributes = wc_get_attribute_taxonomies();
        $enabled_attrs = get_option( 'ff_enabled_attributes', array() );
        $show_price = get_option( 'ff_show_price_filter', '1' );

        ?>
        <div class="wrap">
            <h1>Cài đặt Flatsome Filter</h1>
            <form method="post">
                <?php wp_nonce_field( 'ff_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Lọc theo giá</th>
                        <td>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="checkbox" name="show_price_filter" value="1" <?php checked( $show_price, '1' ); ?>>
                                Hiển thị bộ lọc giá
                            </label>
                            
                            <label><strong>Các khoảng giá (định dạng: min-max | Nhãn)</strong></label><br>
                            <textarea name="ff_price_ranges" rows="5" class="large-text" placeholder="0-500000 | Dưới 500k&#10;500000-1000000 | 500k - 1tr&#10;1000000-0 | Trên 1tr"><?php echo esc_textarea( get_option( 'ff_price_ranges', '' ) ); ?></textarea>
                            <p class="description">Nhập mỗi khoảng giá trên một dòng. Dùng số 0 nếu không giới hạn đầu/cuối.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Các thuộc tính hiển thị</th>
                        <td>
                            <?php 
                            $all_attributes = wc_get_attribute_taxonomies();
                            $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
                            $found_attrs = array();

                            // Cách 1: Qua WooCommerce API
                            if ( ! empty( $all_attributes ) ) {
                                foreach ( $all_attributes as $attr ) {
                                    $found_attrs['pa_' . $attr->attribute_name] = $attr->attribute_label;
                                }
                            }

                            // Cách 2: Quét tất cả pa_ taxonomies (phòng hờ)
                            foreach ( $taxonomies as $tax ) {
                                if ( strpos( $tax->name, 'pa_' ) === 0 && !isset($found_attrs[$tax->name]) ) {
                                    $found_attrs[$tax->name] = $tax->label;
                                }
                            }

                            if ( empty( $found_attrs ) ) {
                                echo '<p style="color: #d63638;">⚠️ Không tìm thấy thuộc tính sản phẩm nào (pa_...). Hãy đảm bảo bạn đã tạo thuộc tính trong <strong>Sản phẩm -> Các thuộc tính</strong>.</p>';
                            } else {
                                foreach ( $found_attrs as $tax_name => $label ) : 
                                    $attr_key = str_replace('pa_', '', $tax_name);
                                ?>
                                    <label style="display: block; margin-bottom: 8px; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 5px; max-width: 400px;">
                                        <input type="checkbox" name="enabled_attributes[]" value="<?php echo esc_attr( $attr_key ); ?>" <?php checked( in_array( $attr_key, $enabled_attrs ) ); ?>>
                                        <strong><?php echo esc_html( $label ); ?></strong> <code style="float: right; opacity: 0.6;"><?php echo esc_html( $tax_name ); ?></code>
                                    </label>
                                <?php endforeach; 
                            } ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="ff_save_settings" class="button button-primary" value="Lưu cài đặt">
                </p>
            </form>
        </div>
        <?php
    }
}

// Khởi chạy plugin
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Flatsome_Filter::get_instance();
    }
} );
