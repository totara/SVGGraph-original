<?php
/**
 * Copyright (C) 2010-2011 Graham Breach
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

require_once('SVGGraphPieGraph.php');

class Pie3DGraph extends PieGraph {

	protected $depth = '40'; // pixels

	public function Draw()
	{
		// modify pad_bottom to make PieGraph do the hard work
		$pb = $this->pad_bottom;
		$this->pad_bottom += $this->depth;
		$this->Calc();
		$this->pad_bottom = $pb;
		return PieGraph::Draw();
	}

	/**
	 * Override the parent to draw 3D slice
	 */
	protected function GetSlice($angle1, $angle2, &$attr)
	{
		$x1 = $y1 = $x2 = $y2 = 0;
		$this->CalcSlice($angle1, $angle2, $x1, $y1, $x2, $y2);

		$outer = ($angle2 - $angle1 > pi() ? 1 : 0);
		$sweep = ($this->reverse ? 0 : 1);

		$path = '';
		if($this->LowerHalf($angle1) || $this->LowerHalf($angle2)) {
			// if the edge is in the top half, need to truncate to x-radius
			$a1 = $this->LowerHalf($angle1) ? $angle1 : pi(); 
			$a2 = $this->LowerHalf($angle2) ? $angle2 : pi(); 
			$path .= $this->GetEdge($a1, $a2);
		}
		if((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2) {
			$attr1 = array('d' => $path);
			$attr2 = array(
				'cx' => $this->x_centre, 'cy' => $this->y_centre,
				'rx' => $this->radius_x, 'ry' => $this->radius_y
			);
			return $this->Element('g', $attr, NULL, 
				$this->Element('path', $attr1) . $this->Element('ellipse', $attr2));
		} else {
			$outer = ($angle2 - $angle1 > pi() ? 1 : 0);
			$sweep = ($this->reverse ? 0 : 1);
			$attr['d'] = $path . "M{$this->x_centre},{$this->y_centre} L$x1,$y1 A{$this->radius_x} {$this->radius_y} 0 $outer,$sweep $x2,$y2 z";
			return $this->Element('path', $attr);
		}
		$path .= "M{$this->x_centre},{$this->y_centre} L$x1,$y1 A{$this->radius_x} {$this->radius_y} 0 $outer,$sweep $x2,$y2 z";
		return $path;
	}

	/**
	 * Returns the path for an edge
	 */
	protected function GetEdge($angle1, $angle2)
	{
		$x1 = $y1 = $x2 = $y2 = 0;
		$this->CalcSlice($angle1, $angle2, $x1, $y1, $x2, $y2);
		$y2a = $y2 + $this->depth;

		$outer = ($angle2 - $angle1 > pi() ? 1 : 0);
		$sweep = ($this->reverse ? 0 : 1);
		$rsweep = $sweep ? 0 : 1;

		return "M$x1,$y1 l0,{$this->depth} A{$this->radius_x} {$this->radius_y} 0 $outer,$sweep $x2,$y2a l0,-{$this->depth} ";
	}

	/**
	 * Returns TRUE if the angle is in the lower half of the pie
	 */
	protected function LowerHalf($angle)
	{
		return ($this->reverse && $angle > pi()) || (!$this->reverse && $angle < pi());
	}

}

