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


function cmp_results($a, $b) {
    $as = str_pad($a[3], 10, "0", STR_PAD_LEFT);
    $bs = str_pad($b[3], 10, "0", STR_PAD_LEFT);
    if ($as == $bs) {
        return 0;
    }
    if ( preg_match('/kn/i', $bs) != 1 ) {
        return -1;
    }
    if ( preg_match('/kn/i', $as) != 1 ) {
        return -1;
    }
    return ($as > $bs) ? -1 : 1;
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
    $years = array();
    $header_classes = "";

    foreach ($Reader as $Row)
    {

        // Sortable results list
        array_push($results, $Row);

        // race classes
        $classes[$Row[1]] = $Row[1];
        $header_classes .= "speed_result_$Row[1] ";

        // race years
        $matches = array();
        if ( preg_match('/\d\d\d\d/', $Row[0], $matches) == 1) {
            $year = $matches[0];
            $years[$year] = $year;
        }
    }
    array_shift($classes);
    //$results_header = array_shift($results);

    sort($years);
    sort($classes);

    uasort ( $results, 'cmp_results' );

    // Render page
    $speed_output .= <<<'EOT'
    <script type="text/javascript">
    var $sj = jQuery.noConflict();
    $sj(document).ready(function() {
        $sj('.button_speed').click(function() {
            try {
                // Filter results list by class and year
                $sj("." + this.name).removeClass("button_selected");
                $sj(this).addClass("button_selected");

                var result_class = $sj('.button_selected.button_class')[0].value;
                var result_year = $sj('.button_selected.button_year')[0].value;

                // calculate new positions
                var position_count = 1;
                $sj(".result_class_" + result_class + '.result_year_' + result_year + ' .speed_result_column_0 div').each(function(index,el) {
                    $sj(el).html(position_count + '.');
                    position_count = position_count + 1;
                });

                // hide all results
                $sj(".speed_result_row").fadeOut();
                // show filtered results
                $sj(".result_class_" + result_class + '.result_year_' + result_year).delay(200).fadeIn();

            } catch(e) { console.log(e) }
        });
        var year_buttons = $sj('.button_year');
        year_buttons[year_buttons.length - 1].click();
    });
    </script>
EOT;

    // Buttons for filtering results
    $speed_output .= '<div>';
    $speed_output .= "<input type='button' name='button_class' value='Luokka' class='first button_speed button_class button_selected'/>\n";
    foreach ($classes as $class) {
        $speed_output .= "<input type='button' name='button_class' value='$class' class='button_speed button_class'/>\n";
    }
    $speed_output .= '</div>';
    $speed_output .= '<div>';
    $speed_output .= "<input type='button' name='button_year' value='Vuosi' class='first button_speed button_year button_selected'/>\n";
    foreach ($years as $year) {
        $speed_output .= "<input type='button' name='button_year' value='$year' class='button_speed button_year button_year_$year'/>\n";
    }
    $speed_output .= '</div>';

    // Results table
    // first row th, others td
    $position = 0;

    $speed_output .= '<table class="speed_result_table">';

    $speed_output .= '<col class="speed_result_column_0" />';
    $speed_output .= '<col class="speed_result_column_1" />';
    $speed_output .= '<col class="speed_result_column_2" />';
    $speed_output .= '<col class="speed_result_column_3" />';
    $speed_output .= '<col class="speed_result_column_4" />';

    foreach ($results as $result) {

        // race years
        $matches = array();
        preg_match('/\d\d\d\d/', $result[0], $matches);
        $year = $matches[0];

        // Position
        if ( $position < 1 ) {
            $speed_output .= "<tr class='speed_result_header'>";
            $speed_output .= "<td class='speed_result_column speed_result_column_0'><div>#</div></td>";
        } else {
            $speed_output .= "<tr class='speed_result_row result_year_Vuosi result_class_Luokka result_class_$result[1] result_year_$year'>";
            $speed_output .= "<td class='speed_result_column speed_result_column_0'><div>$position.</div></td>";
        }

        $position += 1;

        // Name of competitor
        $speed_output .= "<td class='speed_result_column speed_result_column_1'><div>$result[2]</div><div>$result[1]</div></td>";

        // Speed list
        $speed_output .= "<td class='speed_result_column speed_result_column_2'><div>$result[3]</div><div>$result[4]</div></td>";

        // Date, place, conditions
        $speed_output .= "<td class='speed_result_column speed_result_column_3'><div>$result[0]</div><div>$result[6]</div><div>$result[7]</div></td>";

        // Equipment, class, doppler
        $speed_output .= "<td class='speed_result_column speed_result_column_4'><div>$result[8]</div></td>";

        $speed_output .= "</tr>";

    }

    if ( $position == 0) {
        $speed_output .= "<tr><td colspan=5>Ei l&ouml;ytynyt yht&auml;&auml;n tulosta.</td></tr>";
    }

    $speed_output .= "</table>";


    if ( preg_match( '/xls$/', $results_file) == 1) {
        return utf8_encode($speed_output);
    } else {
        return $speed_output;
    }
}

?>
