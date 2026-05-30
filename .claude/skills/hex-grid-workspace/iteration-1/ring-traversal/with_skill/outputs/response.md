# All Hexes Exactly 2 Steps Away from (q=1, r=-2)

Using the hex ring algorithm, a ring at radius N contains exactly **6 * N hexes**. For radius 2, that is **12 hexes**.

## Method: `hex_ring` Algorithm

**Center:** q=1, r=-2
**Radius:** 2

**Starting hex** — 2 steps in direction 4 (SW = (-1, 1)):
```
start = (1, -2) + (-2, 2) = (-1, 0)
```

**Traverse 6 sides, 2 steps each** (collecting j=0 and j=1 on each side):

| Side | Direction     | Hexes collected       |
|------|---------------|-----------------------|
| 0    | E  ( 1,  0)  | (-1, 0), (0, 0)       |
| 1    | NE ( 1, -1)  | (1, 0), (2, -1)       |
| 2    | NW ( 0, -1)  | (3, -2), (3, -3)      |
| 3    | W  (-1,  0)  | (3, -4), (2, -4)      |
| 4    | SW (-1,  1)  | (1, -4), (0, -3)      |
| 5    | SE ( 0,  1)  | (-1, -2), (-1, -1)    |

## The 12 Hexes at Distance 2

| #  | q  | r  |
|----|----|----|
| 1  | -1 |  0 |
| 2  |  0 |  0 |
| 3  |  1 |  0 |
| 4  |  2 | -1 |
| 5  |  3 | -2 |
| 6  |  3 | -3 |
| 7  |  3 | -4 |
| 8  |  2 | -4 |
| 9  |  1 | -4 |
| 10 |  0 | -3 |
| 11 | -1 | -2 |
| 12 | -1 | -1 |

**Verification** using `hex_distance(a, b) = (|dq| + |dq+dr| + |dr|) / 2`:
- (-1, 0): dq=-2, dr=2 → (2+0+2)/2 = **2** ✓
- (3, -4): dq=2, dr=-2 → (2+0+2)/2 = **2** ✓
- (3, -2): dq=2, dr=0 → (2+2+0)/2 = **2** ✓
- (0, -3): dq=-1, dr=-1 → (1+2+1)/2 = **2** ✓