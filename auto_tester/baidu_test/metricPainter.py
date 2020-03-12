# -*- coding: utf-8 -*-
# @Author   :Chen Bo
# @Time     :2020.3.12
# @Software :MetricsMonitorPainterScript

import subprocess
import re
import logging
import time
from matplotlib import pyplot as plt


class MetricsTester:
    def __init__(self, dure_time: float, target: str):
        self.target = target
        self.fig = plt.figure()
        self.time = time
        self.pid_rows, self.cpu_position, self.title_position, self.pid_position, self.mem_position, self.virt_position = -1, -1, -1, -1, -1, -1
        logging.basicConfig(level=logging.INFO,
                            format='%(asctime)s %(levelname)s %(message)s', )
        self.pid_list = []
        self.dure_time = dure_time

    @classmethod
    def call_sys(cls, command):
        ret = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
        return ret.stdout.read().decode('utf-8')

    def get_title_info(self):
        logging.info("Finding PID Rows...")
        #   获取PID所在行数
        for i in range(0, 10):
            command = 'adb shell top -n 1|awk "NR==%d{print}"' % i
            ret = self.call_sys(command)
            if 'PID' in ret:
                self.rows = i
                break
        logging.info("PID Rows found in {} row.".format(self.rows))
        logging.info("Finding other info...")
        #   获取PID、CPU、MEMORY、VIRT所在列数
        title_row = self.call_sys('adb shell top -n 1|awk "NR==%d{print}"' % self.rows)
        title_list = re.split(r"\s+", title_row)
        for index, title in enumerate(title_list):
            if title == 'PID':
                self.pid_position = index -1
            if title == 'VIRT':
                self.virt_position = index -1
            if "CPU" in title:
                self.cpu_position = index
            if "MEM" in title:
                self.mem_position = index
        logging.info("Finding cpu in {},pid in {},virt in {}, memory in {}".format(self.cpu_position, self.pid_position,
                                                                                   self.virt_position,
                                                                                   self.mem_position))

    def get_data(self):
        logging.info("Init..")
        self.get_title_info()
        start_time = time.time()
        #   采样时间
        while time.time() - start_time < self.dure_time:
            #   获取云电视进程信息
            command = "adb shell top -n 1 | grep {}".format(
                self.target)  # %self.title_position # | awk '{print $%d}'tv.danmaku.bili
            ret = self.call_sys(command)
            a = ret.split("\n")
            dicts = {}
            for row in a:
                if row:
                    for index, content in enumerate(re.split(r"\s+", row)):
                        if index == self.cpu_position:
                            dicts['app_cpu_usage'] = content
                        elif index == self.pid_position and content:
                            dicts['app_pid'] = content
                        elif index == self.mem_position:
                            dicts['app_memory_usage'] = content
                        elif index == self.virt_position:
                            dicts['app_virt_usage'] = content.replace('G', '')
                        elif self.target in content:
                            dicts['app_name'] = content
            if dicts:
                self.pid_list.append(dicts)
                logging.info("Catch app info {} ".format(dicts))
            time.sleep(0.4)

    def start(self):
        self.get_data()
        self.create_img()

    def cal_average(self, lists: list) -> float:
        sums = 0
        for i in lists:
            sums += i
        return sums / len(lists)

    def create_img(self):
        x = []
        pid_y = []
        app_cpu_y = []
        app_mem_y = []
        app_virt_y = []

        try:
            for data in self.pid_list:
                if data['app_name'] == self.target:
                    pid_y.append(data['app_pid'])
                    app_cpu_y.append(float(data['app_cpu_usage']))
                    app_mem_y.append(float(data['app_memory_usage']))
                    app_virt_y.append(data['app_virt_usage'])
            [x.append(i) for i in range(len(app_cpu_y))]
        except Exception as e:
            print("err is: ", e)
        try:
            plt.figure(figsize=(19.2, 10.8))
            plt.rcParams['figure.dpi'] = 300
            plt.subplot(411)

            plt.plot(x, pid_y, color="purple")
            plt.legend(['PID'])
            plt.subplot(412)

            plt.plot(x, app_cpu_y, color="red")
            plt.legend(['CPU USAGE(%)'])
            plt.subplot(413)
            print("mem:", app_mem_y)
            plt.plot(x, app_mem_y, color="green")
            plt.legend(['MEMORY USAGE(%)'])
            plt.subplot(414)
            print("virt:", app_virt_y)
            plt.plot(x, app_virt_y)
            plt.legend(['VIRT USAGE(G)'])
            plt.show()
            avg_cpu = self.cal_average(app_cpu_y)
            avg_mem = self.cal_average(app_mem_y)
            print("应用cpu平均使用率为:%.2f" % avg_cpu + "%")
            print("应用内存平均使用率为:%.2f" % avg_mem + "%")
        except Exception as e:
            print("Paint failed error is: ", e)


if __name__ == '__main__':
    mt = MetricsTester(5, "tv.danmaku.bili")  # 输入采样持续时间，单位秒
    mt.start()
    while True:
        key = input("")
        if key == "q" or key == "Q":
            break
