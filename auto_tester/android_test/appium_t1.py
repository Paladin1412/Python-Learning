from appium import webdriver
import time
from xml.dom.minidom import parse


class AppAutoTester:
    def __init__(self):
        desired_caps = {}
        desired_caps['platformName'] = 'Android'  # 系统名称
        desired_caps['platformVersion'] = '6.0'  # 系统的版本号
        desired_caps['deviceName'] = 'xiaomi'  # 设备名称，这里是虚拟机，这个没有严格的规定
        desired_caps['appPackage'] = 'com.xiaodianshi.tv.yst'  # APP包名
        desired_caps['appActivity'] = '.ui.main.MainActivity'  # APP入口的activity
        # 连接appium server，告诉appium，代码要操作哪个设备上的哪个APP
        self.driver = webdriver.Remote('http://127.0.0.1:4723/wd/hub', desired_caps)

    def swipeDown(self):
        time.sleep(3)
        x,y = self.driver.get_window_size()['width'],self.driver.get_window_size()['height']
        self.driver.swipe(1/2*x, 1/7*y, 1/2*x, 6/7*y, 500)

    def case1(self):
        banners = self.driver.find_elements_by_id("com.xiaodianshi.tv.yst:id/title")
        for index, item in enumerate(banners):
            item.find_element_by_class_name("android.widget.TextView").click()
            time.sleep(1)
    def get_source(self):
        source = self.driver.page_source
        retXml = parse(source)

        print(source)
    def __enter__(self):
        print("begin")

    def __exit__(self, exc_type, exc_val, exc_tb):
        print("Closing...")
        self.driver.quit()


if __name__ == '__main__':
    aat = AppAutoTester()
    with aat:
        aat.case1()
# 跳转到浏览器
# driver.start_activity('com.android.browser', '.BrowserActivity')

# 获取包名
# cp = driver.current_package
# 获取界面名
# ca = driver.current_activity
# print(cp, ca)

# 判断是否安装app
# if driver.is_app_installed("com.mumu.store"):
#     driver.remove_app("com.mumu.store")#移除安装的应用
# else:
#     driver.install_app("/Users/michael/Desktop/app")#安装app
# driver.close_app()#只关闭操作的app，不关闭驱动对象

# 将应用置于后台5秒钟再返回前台
# driver.background_app(5)
# time.sleep(5)
# driver.close_app()#只关闭操作的app，不关闭驱动对象


# driver.quit()  # 关闭驱动对象，同时关闭所有关联的app
