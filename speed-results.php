<?php

/*
Plugin Name: Finnish Speed Challenge Results
Plugin URI: http://www.purjelautaliitto.fi
Description: Shortcodes to format Finnish Speed Challenge result lists. See readme.txt for documentation.
Version: 0.1
Author: Mikko Vatanen
Author URI: http://www.purjelautaliitto.fi

Copyright (C) 2013 Mikko Vatanen <mikko@vapaatyyli.fi>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

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
    if ( preg_match('/kn/', $bs) != 1 ) {
        return 1;
    }
    if ( preg_match('/kn/', $as) != 1 ) {
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

    $results_sorted = array();

    foreach ($Reader as $Row) {
        array_push($results_sorted, $Row);;
    }

    $header_row = array_shift($results_sorted);

    uasort ( $results_sorted, 'cmp_results' );

    foreach ($results_sorted as $Row) {

        // All race classes
        $classes[$Row[1]] = $Row[1];
        $header_classes .= "speed_result_$Row[1] ";

        // All race years
        if ( preg_match('/\d\d\d\d/', $Row[0], $matches) == 1) {
            $year = $matches[0];
            $years[$year] = $year;
        } else {
            continue;
        }

        // Competitor
        $competitor = preg_replace('/[^a-z]/', '', strtolower($Row[2]));

        // Best result per competitor for current year stats
        $current_result = str_pad($Row[3], 10, "0", STR_PAD_LEFT);
        $stored_result = str_pad($results[$year][$competitor][3], 10, "0", STR_PAD_LEFT);

        if ( $current_result > $stored_result ) {
            $results[$year][$competitor] = $Row;
        }

        // Best result per competitor for all time stats
        $current_result = str_pad($Row[3], 10, "0", STR_PAD_LEFT);
        $stored_result = str_pad($results['Vuosi'][$competitor][3], 10, "0", STR_PAD_LEFT);

        if ( $current_result > $stored_result ) {
            $results['Vuosi'][$competitor] = $Row;
        }

    }

    sort($years);
    sort($classes);

    // Render page

    // jQuery for dynamic table filtering
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


                // display only filtered results
                $sj(".speed_result_row").fadeOut();

                // calculate new positions
                var position_count = 1;
                $sj(".result_class_" + result_class + '.result_year_' + result_year + ' .speed_result_column_0 div').each(function(index,el) {
                    $sj(el).html(position_count + '.');
                    position_count = position_count + 1;
                });

                $sj(".result_class_" + result_class + '.result_year_' + result_year).delay(300).fadeIn();

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

    $speed_output .= '<table class="speed_result_table">';

    // fuck tables layout!
    $speed_output .= '<col class="speed_result_column_0" />';
    $speed_output .= '<col class="speed_result_column_1" />';
    $speed_output .= '<col class="speed_result_column_2" />';
    $speed_output .= '<col class="speed_result_column_3" />';
    $speed_output .= '<col class="speed_result_column_4" />';

    // Print Header

    $speed_output .= "<tr class='speed_result_header'>";

    // Position
    $speed_output .= "<td class='speed_result_column speed_result_column_0'><div>#</div></td>";

    // Name of competitor
    $speed_output .= "<td class='speed_result_column speed_result_column_1'><div>$header_row[2]</div><div>$header_row[1]</div></td>";

    // Speed list
    $speed_output .= "<td class='speed_result_column speed_result_column_2'><div>$header_row[3]</div><div>$header_row[4]</div></td>";

    // Date, place, conditions
    $speed_output .= "<td class='speed_result_column speed_result_column_3'><div>$header_row[0]</div><div>$header_row[5]</div><div>$header_row[6]</div></td>";

    // Equipment, class, doppler
    $speed_output .= "<td class='speed_result_column speed_result_column_4'><div>$header_row[7]</div></td>";

    $speed_output .= "</tr>";

    // Print Results
    foreach ($results as $year => $year_results) {
        $position = 1;
        foreach ($year_results as $result) {

                $speed_output .= "<tr class='speed_result_row result_class_Luokka result_class_$result[1] result_year_$year'>";

                // Position
                $speed_output .= "<td class='speed_result_column speed_result_column_0'><div>$position.</div></td>";
                $position += 1;

                // Name of competitor
                $speed_output .= "<td class='speed_result_column speed_result_column_1'><div>$result[2]</div><div>$result[1]</div></td>";

                // Speed list
                $speed_output .= "<td class='speed_result_column speed_result_column_2'><div>$result[3]</div><div>$result[4]</div></td>";

                // Date, place, conditions
                $speed_output .= "<td class='speed_result_column speed_result_column_3'><div>$result[0]</div><div>$result[5]</div><div>$result[6]</div></td>";

                // Equipment, class, doppler
                $speed_output .= "<td class='speed_result_column speed_result_column_4'><div>$result[7]</div></td>";

                $speed_output .= "</tr>";
        }
    }

    $speed_output .= "</table>";


    if ( preg_match( '/xls$/', $results_file) == 1) {
        return utf8_encode($speed_output);
    } else {
        return $speed_output;
    }
}

?>
