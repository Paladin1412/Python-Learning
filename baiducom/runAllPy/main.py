import os
import subprocess

[subprocess.call("python {}".format(file)) for file in os.listdir(r"C:\Users\chenbo16\Desktop\server-cases\imetest\apis")]