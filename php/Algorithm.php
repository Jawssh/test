<?php
// php/Algorithm.php

/**
 * Implements the Jenks Natural Breaks algorithm.
 *
 * @param array $data An array of numerical data points (your SES scores).
 * @param int $num_classes The desired number of classes.
 * @return array An array of break points (class limits). The array will contain $num_classes + 1 values,
 * including the minimum data value as the first break and the maximum as the last.
 * Returns an empty array or false on error or if data cannot be classified.
 */
function getJenksBreaks(array $data, int $num_classes): array {
    if (empty($data) || $num_classes <= 0) {
        return [];
    }

    // Remove duplicates and sort the data
    $data = array_unique($data);
    sort($data);

    $count = count($data);

    // Handle cases where data points are fewer than classes
    if ($count === 0) {
        return [];
    }
    if ($num_classes >= $count) {
        // If classes are more than or equal to data points, each point is a break
        // or if only one class, min and max are the breaks
        $breaks = $data; 
        if ($num_classes === 1 && $count > 0) {
             return [$data[0], $data[$count -1]];
        }
        // This edge case handling ensures a valid break array is returned
        // when the number of unique data points is less than or equal to num_classes.
        if ($count > 0) {
            $all_breaks = [$data[0]]; // Start with the minimum
            for ($i=0; $i < $count-1; $i++) {
                 // Add intermediate unique values as potential breaks
                if ($data[$i] !== $all_breaks[count($all_breaks)-1]) { // Ensure no duplicate breaks if data values are same
                    $all_breaks[] = $data[$i]; 
                }
            }
            // Add the maximum value if it's not already the last break
            if ($data[$count-1] !== $all_breaks[count($all_breaks)-1]) {
                 $all_breaks[] = $data[$count-1];
            }
            // If we still don't have enough breaks (e.g., only 2 unique points for 5 classes),
            // we might just return the unique sorted data points as the best possible breaks.
            // The user of this function will need to be aware of this if num_classes is high relative to unique data points.
            return array_values(array_unique($all_breaks)); 
        } else {
            return [];
        }
    }

    // Initialize matrices
    $mat1 = array_fill(0, $count, array_fill(0, $num_classes, 0.0)); // Stores sum of squared deviations
    $mat2 = array_fill(0, $count, array_fill(0, $num_classes, 0.0)); // Stores backtrack pointers
    
    // Fill first class
    // Sum of squared deviations for the first class (j=0)
    // mat1[i][0] will store the SSD for data points data[0]...data[i]
    for ($i = 0; $i < $count; $i++) {
        $sum = 0;
        $sumSq = 0;
        $numElements = 0;
        for ($k = 0; $k <= $i; $k++) {
            $sum += $data[$k];
            $sumSq += $data[$k] * $data[$k];
            $numElements++;
        }
        $mat1[$i][0] = $sumSq - ($sum * $sum) / $numElements; // SSD for the first class ending at i
    }

    // Fill other classes
    for ($j = 1; $j < $num_classes; $j++) { // For each subsequent class (class 1 up to num_classes-1)
        for ($i = 0; $i < $count; $i++) { // For each data point i (potential end of current class j)
            $minSsd = PHP_FLOAT_MAX;
            $breakPoint = -1; 
            // k is the end of the previous class (j-1)
            for ($k = 0; $k < $i; $k++) { 
                $currentSsd = $mat1[$k][$j-1]; // SSD of previous classes, ending at k

                // Calculate SSD for the current range (from k+1 to i) for class j
                $rangeSum = 0;
                $rangeSumSq = 0;
                $elementsInRange = 0;
                for ($m = $k + 1; $m <= $i; $m++) {
                    $rangeSum += $data[$m];
                    $rangeSumSq += $data[$m] * $data[$m];
                    $elementsInRange++;
                }

                if ($elementsInRange > 0) {
                     $currentSsd += $rangeSumSq - ($rangeSum * $rangeSum) / $elementsInRange;
                } // else, no elements in this theoretical range, SSD contribution is 0 for it.

                if ($currentSsd < $minSsd) {
                    $minSsd = $currentSsd;
                    $breakPoint = $k; // Store k as the end index of the previous class
                }
            }
            $mat1[$i][$j] = $minSsd; // Minimum SSD for class j ending at i
            $mat2[$i][$j] = $breakPoint; // Backtrack pointer for class j ending at i
        }
    }

    // Extract breaks by backtracking
    $k = $count - 1; // Start from the last data point
    $breaks = array_fill(0, $num_classes + 1, 0.0);
    $breaks[$num_classes] = $data[$count - 1]; // The last break is always the maximum value

    // Iterate backwards from the second to last class to the first class
    for ($j = $num_classes - 1; $j >= 0; $j--) {
        // The index of the end of this class (which is also the break point)
        // is stored in mat2[k][j]. The actual break value is data[mat2[k][j]].
        // However, breaks are often defined as [min, break_val_1, break_val_2, ..., max]
        // where break_val_1 is the upper bound of class 0.
        // So, breaks[j] should be the upper bound of class j-1 (if j>0)
        // and breaks[0] is min_val.
        
        $breakIndex = (int)$mat2[$k][$j]; // This is the index of the last element of the *previous* class.
        $breaks[$j] = $data[$breakIndex]; // The value at this index is the break.
                                          // For breaks[0] (which is for class 0), this means data[mat2[k][0]]
                                          // If mat2[k][0] is, for example, index for data[0], then breaks[0]=data[0]
        $k = $breakIndex; // Move to the end of the previous class for the next iteration.
    }
    // Ensure breaks[0] is explicitly set to the minimum value of the dataset,
    // as the loop populates breaks[num_classes] down to breaks[0]
    // which typically means breaks[0] from the loop is the upper bound of the *conceptual* class before the first.
    // The true first break point in the series is the minimum data value.
    $breaks[0] = $data[0];

    // Post-processing: sort and unique again, just in case, and ensure proper count.
    // This is important if data had very few unique points leading to fewer breaks than expected.
    sort($breaks);
    $final_breaks = array_values(array_unique($breaks));

    // Ensure the number of breaks. If data is sparse, we might have fewer unique breaks than num_classes + 1.
    // For example, if all data points are the same, we'd have one break [value, value].
    // If $num_classes is 1, we should have [min, max].
    if ($num_classes === 1 && count($final_breaks) === 1 && $count > 0) {
        return [$final_breaks[0], $final_breaks[0]]; // e.g. all data points are the same
    }
    
    return $final_breaks;
}
?>