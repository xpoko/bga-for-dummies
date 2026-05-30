# Hex Ring Traversal: All Hexes at Distance 2

## Setup

**Center hex:** q=1, r=-2
**Ring radius:** 2
**Expected count:** 6 * 2 = 12 hexes

---

## Method: Ring Walk Algorithm

In axial coordinates, the 6 cardinal directions are:

| Index | dq | dr | Name      |
|-------|----|----|-----------|
| 0     | +1 |  0 | East      |
| 1     | +1 | -1 | NorthEast |
| 2     |  0 | -1 | NorthWest |
| 3     | -1 |  0 | West      |
| 4     | -1 | +1 | SouthWest |
| 5     |  0 | +1 | SouthEast |

**Steps:**
1. Move R=2 steps in direction 4 (SouthWest) from the center to find the starting corner.
2. Traverse 6 sides, each of R=2 steps, using directions 0 through 5 in order.

**Starting corner:** (1 + 2*(−1), −2 + 2*(+1)) = **(−1, 0)**

---

## Ring Walk

### Side 0 — direction (1, 0) — East — from (−1, 0)
- Step 1: **(0, 0)**
- Step 2: **(1, 0)**

### Side 1 — direction (1, -1) — NorthEast — from (1, 0)
- Step 1: **(2, -1)**
- Step 2: **(3, -2)**

### Side 2 — direction (0, -1) — NorthWest — from (3, -2)
- Step 1: **(3, -3)**
- Step 2: **(3, -4)**

### Side 3 — direction (-1, 0) — West — from (3, -4)
- Step 1: **(2, -4)**
- Step 2: **(1, -4)**

### Side 4 — direction (-1, 1) — SouthWest — from (1, -4)
- Step 1: **(0, -3)**
- Step 2: **(−1, -2)**

### Side 5 — direction (0, 1) — SouthEast — from (−1, -2)
- Step 1: **(−1, -1)**
- Step 2: **(−1, 0)** (back to start — not added again)

---

## Result: All 12 Hexes at Distance 2 from (q=1, r=−2)

| #  | q  | r  |
|----|----|----|
|  1 |  0 |  0 |
|  2 |  1 |  0 |
|  3 |  2 | -1 |
|  4 |  3 | -2 |
|  5 |  3 | -3 |
|  6 |  3 | -4 |
|  7 |  2 | -4 |
|  8 |  1 | -4 |
|  9 |  0 | -3 |
| 10 | -1 | -2 |
| 11 | -1 | -1 |
| 12 | -1 |  0 |

---

## Verification

Axial distance formula: **distance = (|dq| + |dq + dr| + |dr|) / 2**

- **(0, 0):** dq=−1, dr=2 → (1 + 1 + 2)/2 = **2** ✓
- **(3, −4):** dq=2, dr=−2 → (2 + 0 + 2)/2 = **2** ✓
- **(−1, 0):** dq=−2, dr=2 → (2 + 0 + 2)/2 = **2** ✓