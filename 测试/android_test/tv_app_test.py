from appium import webdriver
import time
import threading
from queue import Queue
from selenium.webdriver.support.wait import WebDriverWait


class AppAutoTester:
    def __init__(self):
        desired_caps = {'platformName': 'Android', 'platformVersion': '6.0', 'deviceName': 'xiaomi',
                        'appPackage': 'com.xiaodianshi.tv.yst', 'appActivity': '.ui.main.MainActivity',
                        'unicodeKeyboard': True, 'resetKeyboard': True}
        self.queue = Queue(maxsize=100)
        # 连接appium server，告诉appium，代码要操作哪个设备上的哪个APP
        self.driver = webdriver.Remote('http://127.0.0.1:4723/wd/hub', desired_caps)

    def __enter__(self):
        print("begin")

    def __exit__(self, exc_type, exc_val, exc_tb):
        print("Closing...")
        self.driver.quit()

    def swipeDown(self):
        end = '返回顶部'
        x, y = self.driver.get_window_size()['width'], self.driver.get_window_size()['height']
        while end:
            self.driver.swipe(x * 0.5, y * 0.99, x * 0.5, y * 0.01)
            source = self.driver.page_source
            if end in source:
                break

    def case1(self):
        banners = self.driver.find_elements_by_id("com.xiaodianshi.tv.yst:id/title")
        for index, item in enumerate(banners):
            print(index)
            item.find_element_by_class_name("android.widget.TextView").click()
            time.sleep(1)
            self.swipeDown()

    def get_source(self):
        source = self.driver.page_source

        print(source)


if __name__ == '__main__':
    aat = AppAutoTester()
    with aat:
        aat.case1()
