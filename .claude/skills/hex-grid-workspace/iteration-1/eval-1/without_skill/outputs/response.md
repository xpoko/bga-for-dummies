# Flat-Top Hex Grid: Pixel to Hex Conversion

**Setup:** flat-top hexagons, size = 60px, grid center = (400, 300), click = (523, 187)

## Step 1: Translate to origin

Shift the click point relative to the grid center:

```
dx = 523 - 400 = 123
dy = 187 - 300 = -113
```

## Step 2: Apply the flat-top pixel-to-axial formula

For flat-top hexagons, the conversion from pixel offset `(dx, dy)` to fractional axial coordinates `(q, r)` is:

```
q = ( 2/3 * dx ) / s
r = ( -1/3 * dx + sqrt(3)/3 * dy ) / s
```

Substituting values (`s = 60`, `sqrt(3)/3 ≈ 0.57735`):

```
q = (2/3 * 123) / 60 = 82 / 60 = 1.3667

r = (-1/3 * 123 + 0.57735 * (-113)) / 60
  = (-41 + (-65.24)) / 60
  = -106.24 / 60
  = -1.7707
```

## Step 3: Cube coordinate rounding

Convert axial `(q, r)` to cube `(x, y, z)` where `z = -q - r`, then round each and fix the coordinate with the largest rounding error (to satisfy `x + y + z = 0`):

```
frac_x = 1.3667,  frac_z = -1.7707,  frac_y = 0.4040

Initial rounding:  rx=1, ry=0, rz=-2
Sum check: 1 + 0 + (-2) = -1  ← violates constraint, fix needed

Rounding errors:
  x: |1.3667 - 1|    = 0.3667
  y: |0.4040 - 0|    = 0.4040  ← largest
  z: |-1.7707 - (-2)| = 0.2293

Fix y: ry = -rx - rz = -1 - (-2) = 1
```

Final cube: `(x=1, y=1, z=-2)` → axial: `q = 1, r = -2`

**Result: The player clicked hex (q = 1, r = -2) in axial coordinates.**