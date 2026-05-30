# Finding Neighbors of an Offset Coordinate Tile

**Short answer: do not add offsets directly to offset coordinates.** Offset coordinates are broken by design for neighbor math — the deltas you need to add to `col` and `row` depend on whether the current row is odd or even, making the arithmetic fragile and error-prone.

## The Correct Approach: Convert to Axial, Compute, Convert Back

### Step 1: Convert offset (col=3, row=4) to axial

Use the **odd-r** formula (assuming pointy-top hexes, odd rows shifted right):

```
q = col - (row - (row & 1)) / 2
r = row
```

For col=3, row=4: row is even, so `row & 1 = 0`, giving `q = 3 - 2 = 1`, `r = 4`. Axial: **(q=1, r=4)**

### Step 2: Add the 6 axial neighbor direction vectors

```
directions = [( 1, 0), ( 1,-1), ( 0,-1), (-1, 0), (-1, 1), ( 0, 1)]
              // E       NE       NW        W        SW       SE
```

### Step 3: Convert each axial neighbor back to offset

| Direction | Axial (q,r) | Offset (col, row) |
|-----------|-------------|-------------------|
| E         | (2, 4)      | (4, 4)            |
| NE        | (2, 3)      | (3, 3)            |
| NW        | (1, 3)      | (2, 3)            |
| W         | (0, 4)      | (2, 4)            |
| SW        | (0, 5)      | (2, 5)            |
| SE        | (1, 5)      | (3, 5)            |

## Why you cannot just add fixed deltas

The col deltas are not uniform — they depend on row parity. NE from an even row shifts col by 0, but from an odd row it shifts by +1. This is the fundamental problem with offset coordinates. The axial system eliminates it entirely.

**Recommendation:** Store tiles in axial (q, r) internally. Use offset only for display. All neighbor, distance, and path algorithms work cleanly in axial space.