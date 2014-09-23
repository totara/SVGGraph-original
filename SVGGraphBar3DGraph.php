<?php
/**
 * Copyright (C) 2009-2011 Graham Breach
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

require_once('SVGGraph3DGraph.php');

class Bar3DGraph extends ThreeDGraph {

	protected $bar_space = 10;

	public function Draw()
	{
		// make sure project_angle is in range
		if($this->project_angle < 0)
			$this->project_angle = 0;
		elseif($this->project_angle > 90)
			$this->project_angle = 90;

		$assoc = $this->AssociativeKeys();
		$this->CalcAxes($assoc, true);
		$body = $this->Grid();
		$axes = $this->Axes();

		$values = $this->GetValues();

		$block_width = $this->bar_unit_width - $this->bar_space;

		// make the top parallelogram, set it as a symbol for re-use
		list($bx,$by) = $this->Project(0,0,$block_width);
		$top = array(
			'id' => 'bTop',
			'd' => "M0,0 l$block_width,0 l$bx,$by l-$block_width,0 z"
		);
		$this->defs[] = $this->Element('symbol', NULL, NULL, $this->Element('path', $top));
		$top = array('xlink:href' => '#bTop');

		$bnum = 0;
		$bspace = $this->bar_space / 2;
		$ccount = count($this->colours);

		// get the translation for the whole bar
		list($tx, $ty) = $this->Project(0,0,$this->bar_space / 2);
		$group = array('transform' => "translate($tx,$ty)");

		$baseline = $this->height - $this->pad_bottom - $this->y0;
		$bar = array('width' => $block_width);

		$bars = '';
		foreach($values as $key => $value) {
			$bar_pos = $this->GridPosition($key, $bnum);

			if(!is_null($value) && !is_null($bar_pos)) {
				$bar['x'] = $bspace + $bar_pos;
				$bar['height'] = abs($value) * $this->bar_unit_height;
				$bar['y'] = $baseline - ($value > 0 ? $bar['height'] : 0);
				$this->Bar($value, $bar);

				$top['transform'] = "translate($bar[x],$bar[y])";
				$side_x = $bar['x'] + $block_width;
				$side = array(
					'd' => "M0,0 l$bx,$by l0,$bar[height] l-$bx," . -$by . " z",
					'transform' => "translate($side_x,$bar[y])"
				);
				$group['fill'] = $this->GetColour($bnum % $ccount);
				$top['fill'] = $this->GetColour($bnum % $ccount, TRUE);

				$rect = $this->Element('rect', $bar);
				$bar_top = $this->Element('use', $top);
				$bar_side = $this->Element('path', $side);
				$link = $this->GetLink($key, $rect . $bar_top . $bar_side);

				if($this->show_tooltips)
					$this->SetTooltip($group, $value);
				$bars .= $this->Element('g', $group, NULL, $link);
				unset($group['id']); // make sure a new one is generated
			}
			++$bnum;
		}

		$bgroup = array('fill' => 'none');
		$this->SetStroke($bgroup, 'round');
		$body .= $this->Element('g', $bgroup, NULL, $bars);
		return $body . $axes;
	}

	/**
	 * Fills in the y-position and height of a bar
	 */
	protected function Bar($value, &$bar)
	{
		$y0 = $this->height - $this->pad_bottom - $this->y0;
		$l1 = $this->ClampVertical($y0);
		$l2 = $this->ClampVertical($y0 - ($value * $this->bar_unit_height));
		$bar['y'] = min($l1,$l2);
		$bar['height'] = abs($l1-$l2);
	}

}

