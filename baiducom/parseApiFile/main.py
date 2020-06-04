import os
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass
from threading import Lock
import re


class ParseApiPath:
    def __init__(self):
        self.target_file_list = []
        self.api_list = []
        self.lock = Lock()

    def load_target_file(self):
        for f in os.listdir('./targetFile'):
            self.target_file_list.append("./targetFile/" + f)

    def parse_file(self, file):
        print("Handle: ", file)
        with open(file, 'r') as fp:
            start_flag = False
            for line in fp.readlines():
                ret = re.findall(r"(/.*),", line)
                if ret:
                    if start_flag:
                        self.api_list.append(start_flag + ret[0])
                    else:
                        start_flag = ret[0]
        return 'success'

    def main_process(self):
        self.load_target_file()
        at = [ThreadPoolExecutor(max_workers=4).submit(self.parse_file, i) for i in self.target_file_list]
        # for i in self.target_file_list:
        #     self.parse_file(i)
        for future in as_completed(at):
            ret = future.result()
            print(ret)
        print(self.api_list)


if __name__ == '__main__':
    pap = ParseApiPath()
    pap.main_process()
