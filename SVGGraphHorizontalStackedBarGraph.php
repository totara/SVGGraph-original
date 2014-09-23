<?php
/**
 * Copyright (C) 2011-2013 Graham Breach
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

class HorizontalStackedBarGraph extends HorizontalBarGraph {

  protected $multi_graph;
  protected $legend_reverse = false;

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by HorizontalStackedBarGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_height = ($this->bar_space >= $this->bar_unit_height ? '1' : 
      $this->bar_unit_height - $this->bar_space);
    $bar_style = array();
    $this->SetStroke($bar_style);
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
          $this->Bar($item->value, $bar, $item->value >= 0 ? $xpos : $xneg);
          if($item->value < 0)
            $xneg += $item->value;
          else
            $xpos += $item->value;

          if($bar['width'] > 0) {
            $bar_style['fill'] = $this->GetColour($item, $j % $ccount);

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

