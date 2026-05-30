# Hex Grid Offset Coordinate Neighbors

Finding the 6 neighbors of a hex tile using offset coordinates depends critically on **which offset convention you are using**. There are four common conventions, and they give different results.

## The Problem: Offset Parity Matters

In offset coordinate systems, the neighbor offsets differ depending on whether the tile is in an **even or odd** column (for "col offset" systems) or **even or odd** row (for "row offset" systems). This is the most common source of confusion.

---

## "Odd-r" Offset (horizontal layout, odd rows shifted right)

For `col=3, row=4` — row 4 is even (4 % 2 == 0):

| Neighbor     | col | row |
|--------------|-----|-----|
| Right        | 4   | 4   |
| Left         | 2   | 4   |
| Upper-right  | 4   | 3   |
| Upper-left   | 3   | 3   |
| Lower-right  | 4   | 5   |
| Lower-left   | 3   | 5   |

---

## "Even-r" Offset (horizontal layout, even rows shifted right)

For `col=3, row=4` — row 4 is even (4 % 2 == 0):

| Neighbor     | col | row |
|--------------|-----|-----|
| Right        | 4   | 4   |
| Left         | 2   | 4   |
| Upper-right  | 3   | 3   |
| Upper-left   | 2   | 3   |
| Lower-right  | 3   | 5   |
| Lower-left   | 2   | 5   |

---

## "Odd-q" Offset (vertical layout, odd columns shifted down)

For `col=3, row=4` — col 3 is odd (3 % 2 == 1):

| Neighbor        | col | row |
|-----------------|-----|-----|
| Upper           | 3   | 3   |
| Lower           | 3   | 5   |
| Upper-right     | 4   | 4   |
| Lower-right     | 4   | 5   |
| Upper-left      | 2   | 4   |
| Lower-left      | 2   | 5   |

---

## The Key Takeaway

**You cannot give a single fixed answer without knowing your offset convention.** The deltas change based on whether the current tile's row (or column) is even or odd.

The most reliable approach is a lookup table keyed on parity:

```python
# Example: odd-r offset (flat-top hexes, odd rows shifted right)
def get_neighbors(col, row):
    if row % 2 == 1:  # odd row
        offsets = [(+1, 0), (-1, 0), (0, -1), (-1, -1), (0, +1), (-1, +1)]
    else:             # even row
        offsets = [(+1, 0), (-1, 0), (+1, -1), (0, -1), (+1, +1), (0, +1)]
    return [(col + dc, row + dr) for dc, dr in offsets]
```

## Recommendation

If you haven't committed to a convention yet, consider using **cube coordinates** internally (x, y, z where x+y+z=0). Neighbors in cube coordinates always use the same 6 fixed offsets regardless of position, eliminating the parity complexity entirely. You can convert to/from offset coordinates for display purposes only.