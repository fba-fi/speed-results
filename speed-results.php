<?php

/*
Plugin Name: Finnish Speed Challenge Results
Plugin URI: http://www.purjelautaliitto.fi
Description: Shortcodes to format Finnish Speed Challenge result lists. See readme.txt for documentation.
Version: 0.1
Author: Mikko Vatanen
Author URI: http://www.purjelautaliitto.fi
*/

function SC_SpeedResults($atts) {
    wp_register_style( 'sr_speed_styles', plugins_url('style.css', __FILE__ ));
    wp_enqueue_style('sr_speed_styles');
    return render_speed_results();
}

add_shortcode("speed_results", "SC_SpeedResults");

// Implementation

function get_results_filename() {
    // Try to find attached results file
    $post = get_post(get_the_ID());
    $args = array(
        'post_type' => 'attachment',
        'numberposts' => null,
        'post_status' => null,
        'post_parent' => $post->ID,
        'suppress_filters' => false
    );
    $attachments = get_posts($args);
    if ($attachments) {
        foreach ($attachments as $attachment) {
            $filename = get_attached_file( $attachment->ID, true );
            if ( preg_match( '/\.(ods|xls)$/', $filename) == 1) {
                return $filename;
            }
        }
    }
    return NULL;
}


function render_speed_results() {

    $speed_output = "";

    // required includes
    require_once('spreadsheet-reader-master/php-excel-reader/excel_reader2.php');
    require_once('spreadsheet-reader-master/SpreadsheetReader.php');

    // Load result Excel/ODS file
    $results_file = get_results_filename();
    if (!$results_file) {
        return "No result XLS/ODS file in attachements";
    }

    try {
        $Reader = new SpreadsheetReader($results_file);
    } catch (Exception $e) {
        return "Error opening open '$results_file': " . $e->getMessage();
    }

    // Populate sorted result list and collect result classes
    $results = array();
    $classes = array();
    $header_classes = "";
    foreach ($Reader as $Row)
    {
        array_push($results, $Row);
        $classes[$Row[1]] = $Row[1];
        $header_classes .= "speed_result_$Row[1] ";
    }
    array_shift($classes);

    // Render page
    $speed_output .= <<<'EOT'
    <script type="text/javascript">
    var $sj = jQuery.noConflict();
    $sj(document).ready(function() {
        $sj('.button_speed').click(function() {
            try {

                $sj(".button_speed").removeClass("button_selected");
                $sj(this).addClass("button_selected");

                $sj("tr.speed_result_row").hide("fade");
                $sj("tr." + this.name).show("fade");

                var position_count = 1;
                $sj("tr." + this.name + " td.result_column_position").each(function(index,el){
                    $sj(el).html(position_count + '.');
                    position_count = position_count + 1;
                });

            } catch(e) { console.log(e) }
        });
    });
    </script>
EOT;

    // Buttons for filtering results
    $speed_output .= "<input type='button' name='speed_result_kaikki' value='kaikki' class='first button_speed button_selected'/>\n";
    foreach ($classes as $class) {
        $speed_output .= "<input type='button' name='speed_result_$class' value='$class' class='button_speed button_speed'/>\n";
    }

    // Results table
    // first row th, others td
    $td = "th";
    $position = 0;
    $speed_output .= '<table class="speed_result_table">';

    $speed_output .= '<colgroup>';
    $speed_output .= '<col style="width:20px">';
    $speed_output .= '<col style="width:100px">';
    $speed_output .= '<col style="width:50px">';
    $speed_output .= '<col style="width:100px">';
    $speed_output .= '<col style="width:100px">';
    $speed_output .= '</colgroup>';

    foreach ($results as $Row) {

        // Position
        if ( $position < 1 ) {
            $speed_output .= "<tr class='speed_result_header'>";
            $speed_output .= "<$td width='20px' class='result_column_position'>#</$td>";
        } else {
            $speed_output .= "<tr class='speed_result_row speed_result_kaikki speed_result_$Row[1]'>";
            $speed_output .= "<$td width='20px' class='result_column_position'>$position.</$td>";
        }
        $position += 1;

        // Name of competitor
        $speed_output .= "<$td>$Row[2]</$td>";

        // Speed list
        $speed_output .= "<$td><div>$Row[3]</div><div>$Row[4]</div><div>$Row[5]</div></$td>";

        // Date, place, conditions
        $speed_output .= "<$td><div>$Row[0]</div><div>$Row[6]</div><div>$Row[7]</div></$td>";

        // Equipment, class, doppler
        $speed_output .= "<$td><div>$Row[8]</div><div>$Row[1]</div><div>($Row[9])</div></$td>";
        $speed_output .= "</tr>";

        // first row th, others td
        $td="td";

    }

    $speed_output .= "</table>";

    if ( preg_match( '/xls$/', $results_file) == 1) {
        return utf8_encode($speed_output);
    } else {
        return $speed_output;
    }
}

?>
