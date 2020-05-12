import datetime
import threading
import time

import matplotlib.pyplot as plt
import psutil
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.wait import WebDriverWait


class MetricsTest:
    def __init__(self, test_url):
        self.ExitFlag = True
        self.cpu_metrics_static = []
        self.status = 'loading page'
        self.test_url = test_url
        self.now_cpu = "%0.2f" % psutil.cpu_percent(interval=1)
        self.now_memory = int(round(psutil.virtual_memory().percent))

    def process_chrome(self):
        driver = webdriver.Chrome()
        locatorLogin = (By.ID, "act-preview-alert-confirm")
        driver.get(self.test_url)
        try:
            WebDriverWait(driver, 20, 0.5).until(EC.presence_of_all_elements_located(locatorLogin))
        except Exception as e:
            print(e)
        driver.find_element_by_id("act-preview-alert-confirm").click()
        main_page = (By.ID, "game")
        try:
            WebDriverWait(driver, 20, 0.5).until(EC.presence_of_all_elements_located(main_page))
        except Exception as e:
            print(e)
        time.sleep(5)
        self.status = 'Up slide page'
        bottom_js = "var q=document.documentElement.scrollTop=100000"
        driver.execute_script(bottom_js)
        video_frame = driver.find_element_by_class_name("video-container")
        driver.execute_script("return arguments[0].scrollIntoView();", video_frame)
        time.sleep(5)
        self.status = 'play video'
        driver.find_element_by_class_name("video-container").click()
        time.sleep(5)
        self.status = 'test slide'
        for i in range(5):
            top_js = "var q=document.documentElement.scrollTop=0"
            driver.execute_script(top_js)
            time.sleep(2)
            driver.execute_script(bottom_js)
            time.sleep(2)
        driver.close()
        self.ExitFlag = False

    def get_metrics_info(self):
        now_time = 0
        while self.ExitFlag:
            cpu_metrics_dict = {}
            data = psutil.virtual_memory()
            # memory = "Memory usage:%d" % (int(round(data.percent))) + "%" + " "
            cpu = "%0.2f" % psutil.cpu_percent(interval=1)
            cpu_metrics_dict['memory'] = int(round(data.percent))
            # cpu_metrics_dict['time'] = now_time
            cpu_metrics_dict['cpu'] = cpu
            cpu_metrics_dict['status'] = self.status
            self.cpu_metrics_static.append(cpu_metrics_dict)
            print(cpu_metrics_dict)

    def paint(self):
        x, y1, y2 = [], [], []
        # x, y1, y2 = [11,2,3,4], [5,8,6,7], [12,24,25,61]
        [y1.append(float(i['cpu'])) for i in self.cpu_metrics_static]
        [y2.append(i['memory']) for i in self.cpu_metrics_static]
        wrong_data = 0
        for i in range(len(y1)):
            try:
                if y1[i] < y1[i + 1] and y1[i + 1] < y1[i + 2]:
                    wrong_data = i
                    break
            except Exception as e:
                print(e)

        for i in range(wrong_data):
            del y1[0]
            del y2[0]

        [x.append(i / 10) for i in range(len(y1))]
        plt.style.use('dark_background')
        fig, ax = plt.subplots()
        ax.plot(x, y1, 'o-', color='#feffb3', label="cpu")
        ax.plot(x, y2, 'o-', color='#8dd3c7', label='memory')
        ax.set_xlabel('Time')
        ax.set_ylabel('Usage(%)')
        plt.show()
        plt.savefig('./ret.png')

    def case(self):
        t1 = threading.Thread(target=self.process_chrome)
        t2 = threading.Thread(target=self.get_metrics_info)
        t2.start()
        t1.start()
        t1.join()
        self.paint()


if __name__ == '__main__':
    t = MetricsTest("https://www.bilibili.com/blackboard/preview/activity-cWV7MyvR.html?anchor=reserve")
    start_time = datetime.datetime.now()
    t.case()
    print((datetime.datetime.now() - start_time).total_seconds())
