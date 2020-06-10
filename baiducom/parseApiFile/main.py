# -*- coding:utf-8 -*-
import os
from concurrent.futures import ThreadPoolExecutor, as_completed
import re
import requests
from threading import Thread
import json
import time


class ParseApiPath:
    def __init__(self):
        self.target_file_list = []
        self.api_list = []
        self.success_file = []
        self.params = {"userName": "zhouzhihui01", "productId": 7400}
        self.sess = requests.session()
        self.sess.verify = False
        self.existed_api = []
        self.not_existed_api = []

    def load_target_file(self):
        for f in os.listdir('./targetFile'):
            if f.endswith('.php'):
                self.target_file_list.append("./targetFile/" + f)

    def parse_file(self, file):
        with open(file, 'r', encoding='utf-8') as fp:
            start_flag = False
            for line in fp.readlines():
                if not start_flag:
                    ret = re.findall(r"@path\(\"(/.*)\"", line)
                    if ret:
                        start_flag = ret[0]
                else:
                    ret = re.findall(r"@route\(\{\".*\",\"/(.*)\"", line)
                    if ret:
                        self.api_list.append("/v5" + start_flag + ret[0])
        return 'success'

    @staticmethod
    def save_to_file(ret):
        with open('ret.txt', 'w', encoding='utf-8') as fp:
            for i in ret:
                fp.write(i)
                fp.write('\n')
            fp.write("共计{}条API".format(len(ret)))

    def get_all_children_suite(self) -> dict:
        url = "http://icase.baidu.com/ws/testCase/casetree/24245530?"  # 获取所有服务端cases
        headers = {"Accept-Encoding": "br"}
        ret = self.sess.get(url, params=self.params, headers=headers)
        if ret.status_code == 200:
            ret = json.loads(ret.text)
            children_suite = ret['retSingleData']['children']
            # print(children_suite)
            return children_suite
        # return {}

    def handle_file(self):
        self.load_target_file()
        [self.success_file.append(future.result()) for future in as_completed([ThreadPoolExecutor(max_workers=4).submit
                                                                               (self.parse_file, i) for i in
                                                                               self.target_file_list])]
        assert len(self.success_file) == len(self.target_file_list), "有文件失败"

        self.save_to_file(self.api_list)

    def get_all_existed_cases(self):
        all_suite = self.get_all_children_suite()
        if isinstance(all_suite, list):
            for suite in all_suite:
                this_suite_children = suite['children']
                this_suite_case = suite['cases']
                [self.existed_api.append(i['title']) for i in this_suite_case if this_suite_case]
                for children in this_suite_children:
                    children_cases = children['cases']
                    for case in children_cases:
                        self.existed_api.append(case['title'])
                        # process = case['process']
                        # parse_ret = re.findall(r"(/v5/*)", process)
                        # if parse_ret:
                        #     self.existed_api.append(parse_ret[0])

    def check_if_existed(self):
        for api in self.api_list:
            if api not in self.existed_api:
                self.not_existed_api.append(api)
        with open("not_existed_diff_case.txt", "w") as fp:
            for i in self.not_existed_api:
                fp.write(i)
                fp.write('\n')

    def main_process(self):
        hf_thread = Thread(target=self.handle_file)
        cie_thread = Thread(target=self.get_all_existed_cases)
        hf_thread.start()
        cie_thread.start()
        hf_thread.join()
        cie_thread.join()
        self.check_if_existed()


if __name__ == '__main__':
    start = time.time()
    pap = ParseApiPath()
    pap.main_process()
    print(time.time() - start)
