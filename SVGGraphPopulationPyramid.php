<?php
/**
 * Copyright (C) 2013 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphHorizontalBarGraph.php';
require_once 'SVGGraphAxisDoubleEnded.php';
require_once 'SVGGraphAxisFixedDoubleEnded.php';

class PopulationPyramid extends HorizontalBarGraph {

  protected $multi_graph;
  protected $legend_reverse = false;

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by PopulationPyramid');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_height = ($this->bar_space >= $this->bar_unit_height ? '1' : 
      $this->bar_unit_height - $this->bar_space);
    $bar_style = array();
    $bar = array('height' => $bar_height);

    $bnum = 0;
    $bspace = $this->bar_space / 2;
    $b_start = $this->height - $this->pad_bottom - ($this->bar_space / 2);
    $ccount = count($this->colours);
    $chunk_count = count($this->multi_graph);

    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;

        $xpos = $xneg = 0;
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          $value = $j % 2 ? $item->value : -$item->value;
          $this->Bar($value, $bar, $value >= 0 ? $xpos : $xneg);
          if($value < 0)
            $xneg += $value;
          else
            $xpos += $value;

          if($bar['width'] > 0) {
            $bar_style['fill'] = $this->GetColour($item, $j % $ccount);
            $this->SetStroke($bar_style, $item, $j);

            if($this->show_tooltips)
              $this->SetTooltip($bar, $item, $item->value, null,
                !$this->compat_events && $this->show_bar_labels);
            $rect = $this->Element('rect', $bar, $bar_style);
            if($this->show_bar_labels)
              $rect .= $this->BarLabel($item, $bar, $j + 1 < $chunk_count);
            $body .= $this->GetLink($item, $k, $rect);
            unset($bar['id']); // clear ID for next generated value

            if(!isset($this->bar_styles[$j]))
              $this->bar_styles[$j] = $bar_style;
          }
        }
      }
      ++$bnum;
    }

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Overridden to prevent drawing behind higher bars
   * $offset_y should be true for inner bars
   */
  protected function BarLabel($item, &$bar, $offset_x = null)
  {
    $content = $item->Data('label');
    if(is_null($content))
      $content = $item->value;
    list($text_size) = $this->TextSize(strlen($content),
      $this->bar_label_font_size, $this->bar_label_font_adjust);
    $space = $this->bar_label_space;
    if($offset_x) {

      // bar too small, would be above
      if($bar['width'] < $text_size + 2 * $space)
        return parent::BarLabel($item, $bar, ($bar['width'] + $text_size)/2);

      // option set to above
      if($this->bar_label_position == 'above') {
        $this->bar_label_position = 'top';
        $label = parent::BarLabel($item, $bar);
        $this->bar_label_position = 'above';
        return $label;
      }
    }
    return parent::BarLabel($item, $bar);
  }

  /**
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    if(!$this->values->error)
      $this->multi_graph = new MultiGraph($this->values, $this->force_assoc,
        $this->require_integer_keys);
  }

  /**
   * Find the longest data set
   */
  protected function GetHorizontalCount()
  {
    return $this->multi_graph->ItemsCount(-1);
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    $sums = array(array(), array());
    $sets = count($this->values);
    if($sets < 2)
      return $this->multi_graph->GetMaxValue();
    for($i = 0; $i < $sets; ++$i) {
      $dir = $i % 2;
      foreach($this->values[$i] as $item) {
        @$sums[$dir][$item->key] += $item->value;
      }
    }
    return max(max($sums[0]), max($sums[1]));
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    $sums = array(array(), array());
    $sets = count($this->values);
    if($sets < 2)
      return $this->multi_graph->GetMinValue();
    for($i = 0; $i < $sets; ++$i) {
      $dir = $i % 2;
      foreach($this->values[$i] as $item) {
        @$sums[$dir][$item->key] += $item->value;
      }
    }
    return min(min($sums[0]), min($sums[1]));
  }

  /**
   * Returns the key from the MultiGraph
   */
  protected function GetKey($index)
  {
    return $this->multi_graph->GetKey($index);
  }

  /**
   * Returns the maximum key from the MultiGraph
   */
  protected function GetMaxKey()
  {
    return $this->multi_graph->GetMaxKey();
  }

  /**
   * Returns the minimum key from the MultiGraph
   */
  protected function GetMinKey()
  {
    return $this->multi_graph->GetMinKey();
  }

  /**
   * Returns the X and Y axis class instances as a list
   */
  protected function GetAxes($ends, &$x_len, &$y_len)
  {
    // always assoc, no units
    $this->units_x = $this->units_before_x = null;

    $max_h = $ends['v_max'];
    $min_h = $ends['v_min'];
    $max_v = $ends['k_max'];
    $min_v = $ends['k_min'];
    $x_min_unit = $this->minimum_units_y;
    $x_fit = false;
    $y_min_unit = 1;
    $y_fit = true;
    $x_units_after = (string)$this->units_y;
    $y_units_after = (string)$this->units_x;
    $x_units_before = (string)$this->units_before_y;
    $y_units_before = (string)$this->units_before_x;

    // sanitise grid divisions
    if(is_numeric($this->grid_division_v) && $this->grid_division_v <= 0)
      $this->grid_division_v = null;
    if(is_numeric($this->grid_division_h) && $this->grid_division_h <= 0)
      $this->grid_division_h = null;

    // if fixed grid spacing is specified, make the min spacing 1 pixel
    if(is_numeric($this->grid_division_v))
      $this->minimum_grid_spacing_v = 1;
    if(is_numeric($this->grid_division_h))
      $this->minimum_grid_spacing_h = 1;

    if(!is_numeric($max_h) || !is_numeric($min_h) ||
      !is_numeric($max_v) || !is_numeric($min_v))
      throw new Exception('Non-numeric min/max');

    if(!is_numeric($this->grid_division_h))
      $x_axis = new AxisDoubleEnded($x_len, $max_h, $min_h, $x_min_unit, $x_fit,
        $x_units_before, $x_units_after);
    else
      $x_axis = new AxisFixedDoubleEnded($x_len, $max_h, $min_h, 
        $this->grid_division_h, $x_units_before, $x_units_after);

    if(!is_numeric($this->grid_division_v))
      $y_axis = new Axis($y_len, $max_v, $min_v, $y_min_unit, $y_fit,
        $y_units_before, $y_units_after);
    else
      $y_axis = new AxisFixed($y_len, $max_v, $min_v, $this->grid_division_v,
        $y_units_before, $y_units_after);

    $y_axis->Reverse(); // because axis starts at bottom
    return array($x_axis, $y_axis);
  }
}

