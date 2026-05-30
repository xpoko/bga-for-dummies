---
name: hex-grid
description: Reference for implementing hexagonal grid algorithms. Use this skill whenever working with hexagonal tiles, hex maps, hex coordinates, or any grid where cells have 6 neighbors. Trigger for: coordinate system selection, neighbor lookups, distance calculations, hex↔pixel conversion, rotation, rings/spirals, line drawing, pathfinding heuristics, or movement ranges on hex grids. Also trigger when debugging coordinate math that involves hex grids or when converting between offset, axial, cube, or doubled coordinate systems.
---

# Hexagonal Grid Reference

Source: https://www.redblobgames.com/grids/hexagons/

## Core Strategy: Axial for Storage, Cube for Math

Store coordinates as **axial (q, r)** — s is always implicit (`s = -q - r`).
Compute neighbors/distance/rotation in **cube (q, r, s)** — the math is cleanest there.

The invariant `q + r + s = 0` must always hold. If it's violated, there's a bug.

---

## Coordinate Systems at a Glance

| System | Structure | Safe to add/subtract? | Best for |
|--------|-----------|----------------------|----------|
| **Axial** | q, r (s implicit) | Yes | Storage, most algorithms |
| **Cube** | q, r, s (q+r+s=0) | Yes | Rotation, distance, ring traversal |
| **Doubled** | col, row (sum even) | Yes | Rectangular maps |
| **Offset** | col, row | **No** | Display only — convert before any math |

Never do math directly on offset coordinates. Always convert to axial first.

---

## Orientation: Pointy-Top vs Flat-Top

**Pointy-top:** vertices at top/bottom, flat edges on left/right.
**Flat-top:** flat edges at top/bottom, vertices on left/right.

This affects hex↔pixel formulas only. Pick one per project and stick to it.

---

## Neighbor Directions

Same direction vectors work for both pointy-top and flat-top in axial/cube:

```
directions = [
  Hex( 1,  0),  // E  (index 0)
  Hex( 1, -1),  // NE (index 1)
  Hex( 0, -1),  // NW (index 2)
  Hex(-1,  0),  // W  (index 3)
  Hex(-1,  1),  // SW (index 4)
  Hex( 0,  1),  // SE (index 5)
]

function hex_neighbor(hex, direction_index):
    return hex_add(hex, directions[direction_index])
```

---

## Distance

### Axial (preferred — no conversion needed)
```
function hex_distance(a, b):
    dq = a.q - b.q
    dr = a.r - b.r
    return (abs(dq) + abs(dq + dr) + abs(dr)) / 2
```

### Cube (equivalent)
```
function cube_distance(a, b):
    return max(abs(a.q-b.q), abs(a.r-b.r), abs(a.s-b.s))
    // same as: (abs(dq) + abs(dr) + abs(ds)) / 2
```

---

## Hex ↔ Pixel Conversion

`size` = distance from center to any vertex (circumradius).

### Pointy-top

**Hex → Pixel:**
```
x = size * (sqrt(3) * q  +  sqrt(3)/2 * r)
y = size * (3/2 * r)
```

**Pixel → Hex (fractional — must round after):**
```
q_frac = (sqrt(3)/3 * x  -  1/3 * y) / size
r_frac = (2/3 * y) / size
```

### Flat-top

**Hex → Pixel:**
```
x = size * (3/2 * q)
y = size * (sqrt(3)/2 * q  +  sqrt(3) * r)
```

**Pixel → Hex (fractional — must round after):**
```
q_frac = (2/3 * x) / size
r_frac = (-1/3 * x  +  sqrt(3)/3 * y) / size
```

**With origin offset:** add `(origin.x, origin.y)` after hex→pixel; subtract before pixel→hex.

---

## Rounding Fractional Hex Coordinates

Pixel→hex always gives floats. Never round each component independently — it breaks the invariant. Use this:

```
function cube_round(frac_q, frac_r):
    frac_s = -frac_q - frac_r

    q = round(frac_q)
    r = round(frac_r)
    s = round(frac_s)

    q_diff = abs(q - frac_q)
    r_diff = abs(r - frac_r)
    s_diff = abs(s - frac_s)

    // Reset the component with largest rounding error to restore q+r+s=0
    if q_diff > r_diff and q_diff > s_diff:
        q = -r - s
    else if r_diff > s_diff:
        r = -q - s
    else:
        s = -q - r

    return Hex(q, r)  // s is implicit
```

---

## Rotation (around origin)

In cube coordinates:

```
60° clockwise:           Cube(q, r, s) → Cube(-r, -s, -q)
60° counter-clockwise:   Cube(q, r, s) → Cube(-s, -q, -r)
```

To rotate around an arbitrary center `c`:
```
1. vec    = hex_subtract(hex, c)
2. rotated = rotate_cube(vec)
3. result  = hex_add(rotated, c)
```

---

## Rings and Spirals

### Single ring at radius N  →  exactly 6·N hexes
```
function hex_ring(center, radius):
    results = []
    // Start at the hex "radius steps in direction 4 (SW)" from center
    hex = hex_add(center, hex_scale(directions[4], radius))
    for i in 0..5:
        for j in 0..radius-1:
            results.append(hex)
            hex = hex_neighbor(hex, i)
    return results
```

### Spiral fill up to radius N  →  1 + 3·N·(N+1) hexes total
```
function hex_spiral(center, radius):
    results = [center]
    for k in 1..radius:
        results += hex_ring(center, k)
    return results
```

---

## Line Drawing

Interpolate in cube space, round at each step:

```
function hex_line(a, b):
    N = hex_distance(a, b)
    results = []
    for i in 0..N:
        t = i / N
        results.append(cube_round(
            lerp(a.q, b.q, t),
            lerp(a.r, b.r, t)
        ))
    return results
```

---

## Movement Range

All hexes within N steps (no obstacles):
```
results = []
for dq in -N..N:
    for dr in max(-N, -dq-N)..min(N, -dq+N):
        results.append(hex_add(center, Hex(dq, dr)))
```

With obstacles: BFS (unweighted) or Dijkstra (weighted) using `hex_neighbor`.

---

## Pathfinding

Identical to square grids — A*, Dijkstra, BFS all apply directly.
A* heuristic: `hex_distance(current, goal) * min_movement_cost`.

---

## Coordinate Conversions

### Offset ↔ Axial

**Odd-r (pointy-top, odd rows shifted right):**
```
offset → axial:   q = col - (row - (row & 1)) / 2,   r = row
axial  → offset:  col = q + (r - (r & 1)) / 2,        row = r
```

**Even-r:** replace `(row & 1)` with `-(row & 1)` (negate the parity term).

**Flat-top (odd-q / even-q):** swap q/r roles in the formulas above.

### Doubled ↔ Axial
```
doublewidth  → axial:  q = col/2,  r = row
axial → doublewidth:   col = 2*q,  row = r

doubleheight → axial:  q = col,    r = row/2
axial → doubleheight:  col = q,    row = 2*r
```

### Axial ↔ Cube
```
axial → cube:  s = -q - r   (just add the third component)
cube  → axial: drop s
```

---

## Map Storage Patterns

| Shape | Storage |
|-------|---------|
| Rhombus | `array[r][q]` — uniform dimensions |
| Rectangular | `array[r][q + floor(r/2)]` (equivalent to odd-r offset) |
| Hex-shaped radius N | Variable-length rows; use hash map for simplicity |
| Arbitrary / sparse | Hash map keyed on `(q, r)` |

---

## Common Pitfalls

- **Offset math is broken by design** — offset coordinates cannot be added or subtracted. Always convert to axial first.
- **Round with `cube_round`, not component-wise** — rounding q, r, s independently violates the invariant and produces wrong hexes.
- **Flat-top vs pointy-top swap x/y roles** — pick one orientation per project; mixing them silently produces wrong pixel positions.
- **Ring starting direction matters** — `hex_ring` must start in direction 4 (SW) at distance `radius` from center, then traverse all 6 sides. Starting elsewhere gives a valid ring but in a different order.
- **`q + r + s = 0` is your canary** — add an assertion anywhere you construct a cube hex during development.