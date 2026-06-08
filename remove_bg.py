import os
import glob
from PIL import Image
import math

def process_image(path):
    print(f"Processing {path}")
    img = Image.open(path).convert("RGBA")
    data = img.getdata()

    new_data = []
    # Target color
    tr, tg, tb = 15, 23, 42
    tolerance = 80

    for item in data:
        r, g, b, a = item
        # Euclidean distance
        dist = math.sqrt((r - tr)**2 + (g - tg)**2 + (b - tb)**2)

        if dist < tolerance:
            # Fully transparent if very close, feathering if further away
            if dist < tolerance - 30:
                new_data.append((r, g, b, 0))
            else:
                alpha = int(((dist - (tolerance - 30)) / 30) * 255)
                new_data.append((r, g, b, alpha))
        else:
            new_data.append((r, g, b, a))

    img.putdata(new_data)
    img.save(path, "PNG")

files = glob.glob("assets/icons/*.png")
for f in files:
    process_image(f)

print("Done")
