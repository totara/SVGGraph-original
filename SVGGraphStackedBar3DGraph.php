<?php
/**
 * Copyright (C) 2012 Graham Breach
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
require_once 'SVGGraphBar3DGraph.php';

class StackedBar3DGraph extends Bar3DGraph {

  protected $multi_graph;
  protected $legend_reverse = true;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_width = ($this->bar_space >= $this->bar_unit_width ? '1' : 
      $this->bar_unit_width - $this->bar_space);
    $bar_style = array();
    $this->SetStroke($bar_style);
    $bar = array('width' => $bar_width);

    $this->block_width = $bar_width;

    // make the top parallelogram, set it as a symbol for re-use
    list($this->bx, $this->by) = $this->Project(0, 0, $this->block_width);
    $top = $this->BarTop();

    $bspace = $this->bar_space / 2;
    $bnum = 0;
    $ccount = count($this->colours);
    $chunk_count = count($this->values);
    $groups = array_fill(0, $chunk_count, '');

    // get the translation for the whole bar
    list($tx, $ty) = $this->Project(0, 0, $this->bar_space / 2);
    $group = array('transform' => "translate($tx,$ty)");
    $bars = '';
    foreach($this->multi_graph->all_keys as $k) {
      $bar_pos = $this->GridPosition($k, $bnum);

      if(!is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;

        // sort the values from bottom to top, assigning position
        $ypos = $yplus = $yminus = 0;
        $chunk_values = array();
        for($j = 0; $j < $chunk_count; ++$j) {
          $value = $this->multi_graph->GetValue($k, $j);
          if(!is_null($value)) {
            if($value < 0) {
              array_unshift($chunk_values, array($j, $value, $yminus));
              $yminus += $value;
            } else {
              $chunk_values[] = array($j, $value, $yplus);
              $yplus += $value;
            }
          }
        }

        $bar_count = count($chunk_values);
        $b = 0;
        foreach($chunk_values as $chunk) {
          $j = $chunk[0];
          $value = $chunk[1];
          $colour = $j % $ccount;
          $v = abs($value);
          $t = ++$b == $bar_count ? $top : null;
          $bar_sections = $this->Bar3D($value, $bar, $t, $colour,
            $chunk[2] * $this->bar_unit_height);
          $ypos = $ty;
          $group['transform'] = "translate($tx," . $ypos . ")";
          $group['fill'] = $this->GetColour($colour);

          if($this->show_tooltips)
            $this->SetTooltip($group, $value);
          $link = $this->GetLink($k, $bar_sections);
          $bars .= $this->Element('g', $group, NULL, $link);
          unset($group['id']); // make sure a new one is generated
          $style = $group;
          $this->SetStroke($style);

          if(!array_key_exists($j, $this->bar_styles))
            $this->bar_styles[$j] = $style;
        }
      }
      ++$bnum;
    }

    $bgroup = array('fill' => 'none');
    $this->SetStroke($bgroup, 'round');
    $body .= $this->Element('g', $bgroup, NULL, $bars);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    $this->multi_graph = new MultiGraph($this->values, $this->force_assoc);
  }

  /**
   * Overridden to prevent drawing behind higher bars
   * $offset_y should be true for inner bars
   */
  protected function BarLabel($value, &$bar, $offset_y = null)
  {
    $font_size = $this->bar_label_font_size;
    $space = $this->bar_label_space;
    if($offset_y) {

      // bar too small, would be above
      if($bar['height'] < $font_size + 2 * $space)
        return parent::BarLabel($value, $bar, ($bar['height'] + $font_size)/2);

      // option set to above
      if($this->bar_label_position == 'above') {
        $this->bar_label_position = 'top';
        $label = parent::BarLabel($value, $bar);
        $this->bar_label_position = 'above';
        return $label;
      }
    }
    return parent::BarLabel($value, $bar);
  }

  /**
   * Find the longest data set
   */
  protected function GetHorizontalCount()
  {
    return $this->multi_graph->KeyCount();
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxSumValue();
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinSumValue();
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

}

