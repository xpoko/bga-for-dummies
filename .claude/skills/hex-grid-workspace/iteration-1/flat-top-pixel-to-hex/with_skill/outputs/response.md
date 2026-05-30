# Flat-Top Pixel to Hex Conversion

**Given:**
- Orientation: flat-top hexagons
- size = 60 px
- Grid center (origin): (400, 300)
- Click pixel: (523, 187)

## Step 1: Translate pixel relative to grid origin

Subtract the origin offset:

```
x = 523 - 400 = 123
y = 187 - 300 = -113
```

## Step 2: Apply flat-top pixel → fractional hex formula

For flat-top hexagons:
```
q_frac = (2/3 * x) / size
r_frac = (-1/3 * x  +  sqrt(3)/3 * y) / size
```

With sqrt(3)/3 ≈ 0.57735:

```
q_frac = (2/3 * 123) / 60  =  82 / 60  =  1.3667

r_frac = (-1/3 * 123  +  0.57735 * (-113)) / 60
       = (-41 + (-65.2406)) / 60
       = -106.2406 / 60
       = -1.7707
```

## Step 3: Apply cube_round (never round components independently)

```
frac_q = 1.3667
frac_r = -1.7707
frac_s = -frac_q - frac_r = 0.4040

Round each:
  q = round(1.3667)  = 1
  r = round(-1.7707) = -2
  s = round(0.4040)  = 0

Check invariant: 1 + (-2) + 0 = -1  ← violated, must correct
```

Find the component with the largest rounding error:
```
q_diff = |1 - 1.3667|     = 0.3667
r_diff = |-2 - (-1.7707)| = 0.2293
s_diff = |0 - 0.4040|     = 0.4040   ← largest
```

Reset s (largest error): `s = -q - r = -1 - (-2) = 1`

Final: q = 1, r = -2, s = 1. Verify: 1 + (-2) + 1 = 0. Invariant holds.

## Result

**The player clicked hex (q = 1, r = -2)** in axial coordinates (cube: q=1, r=-2, s=1).