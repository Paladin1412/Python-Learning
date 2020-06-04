import pandas
import os


def get_target_csv_files() -> list:
    target_csv_files = []
    print(os.listdir("raw_files"))
    for csv_file in os.listdir("./raw_files"):
        if csv_file.endswith(".csv"):
            target_csv_files.append(csv_file)
    print(target_csv_files)
    return target_csv_files

