<?php
/**
 * Copyright (C) 2011-2012 Graham Breach
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

require_once('SVGGraphMultiGraph.php');
require_once('SVGGraphBarGraph.php');

class StackedBarGraph extends BarGraph {

	protected $multi_graph;
	protected $bar_space = 10;
	protected $label_centre = true;

	protected function Draw()
	{
		$assoc = $this->AssociativeKeys();
		$this->CalcAxes($assoc,true);
		$body = $this->Grid();

		$bar_width = ($this->bar_space >= $this->bar_unit_width ? '1' : 
			$this->bar_unit_width - $this->bar_space);
		$bar_style = array();
		$this->SetStroke($bar_style);
		$bar = array('width' => $bar_width);

		$bspace = $this->bar_space / 2;
		$bnum = 0;
		$ccount = count($this->colours);
		$chunk_count = count($this->values);
		foreach($this->multi_graph->all_keys as $k) {
			$bar_pos = $this->GridPosition($k, $bnum);

			if(!is_null($bar_pos)) {
				$bar['x'] = $bspace + $bar_pos;

				$ypos = $yneg = $yplus = $yminus = 0;
				for($j = 0; $j < $chunk_count; ++$j) {
					$value = $this->multi_graph->GetValue($k, $j);
					$this->Bar($value >= 0 ? $value + $yplus : $value - $yminus, $bar);
					if($value < 0) {
						$bar['height'] -= $yneg;
						$bar['y'] += $yneg;
						$yneg += $bar['height'];
						$yminus -= $value;
					} else {
						$bar['height'] -= $ypos;
						$ypos += $bar['height'];
						$yplus += $value;
					}

					if($bar['height'] > 0) {
						$bar_style['fill'] = $this->GetColour($j % $ccount);

						if($this->show_tooltips)
							$this->SetTooltip($bar, $value);
						$rect = $this->Element('rect', $bar, $bar_style);
						$body .= $this->GetLink($k, $rect);
						unset($bar['id']); // clear for next value
					}
				}
			}
			++$bnum;
		}

		$body .= $this->Axes();
		return $body;
	}

	/**
	 * construct multigraph
	 */
	public function Values($values)
	{
		parent::Values($values);
		$this->multi_graph = new MultiGraph($this->values);
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
		$stack = array();
		$chunk_count = count($this->values);

		foreach($this->multi_graph->all_keys as $k) {
			$s = 0;
			for($j = 0; $j < $chunk_count; ++$j) {
				$v = $this->multi_graph->GetValue($k, $j);
				if($v > 0)
					$s += $v;
			}
			$stack[] = $s;
		}
		return max($stack);
	}

	/**
	 * Returns the minimum (stacked) value
	 */
	protected function GetMinValue()
	{
		$stack = array();
		$chunk_count = count($this->values);

		foreach($this->multi_graph->all_keys as $k) {
			$s = 0;
			for($j = 0; $j < $chunk_count; ++$j) {
				$v = $this->multi_graph->GetValue($k, $j);
				if($v <= 0)
					$s += $v;
			}
			$stack[] = $s;
		}
		return min($stack);
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

