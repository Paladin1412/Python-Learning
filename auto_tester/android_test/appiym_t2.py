from appium import webdriver
import time

from selenium.webdriver.support.wait import WebDriverWait

desired_caps = {}
desired_caps['platformName'] = 'Android'  # 系统名称
desired_caps['platformVersion'] = '6.0'  # 系统的版本号
desired_caps['deviceName'] = '127.0.0.1:5554'  # 设备名称，这里是虚拟机，这个没有严格的规定
desired_caps['appPackage'] = 'com.android.settings'  # APP包名
desired_caps['appActivity'] = '.Settings'  # APP入口的activity
desired_caps['unicodeKeyboard']=True #支持中文输入
desired_caps['resetKeyboard']=True
# 连接appium server，告诉appium，代码要操作哪个设备上的哪个APP
driver = webdriver.Remote('http://127.0.0.1:4723/wd/hub', desired_caps)
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

###元素定位###
# 定位一个元素
# driver.find_element_by_id()
# driver.find_element_by_class_name()
# driver.find_element_by_xpath()
# train
# driver.find_element_by_id("com.android.settings:id/search").click()
# driver.find_element_by_class_name("android.widget.EditText").send_keys("ssss")
# driver.find_element_by_xpath("//*[@context-desc='收起]").click()
# 定位一组元素
# textviews = driver.find_element_by_class_name("android.widget.TextView")
# for textview in textviews:
#     print(textview.text)
# eles = driver.find_elements_by_xpath("//*[contains(@text,'设')]")
# for i in eles:
#     print(i.text)

###元素等待###
# 1、隐式等待（全局有效）
# 通常用于针对所有定位元素的超时时间设置为同一个值的时候
# driver.implicitly_wait(200)#等待200秒寻找按钮
# driver.find_elements_by_xpath("//*[@content-desc='收起']").click()
# 2、显式等待（单个寻找有效）
# 通常用于针对所有定位元素的超时时间设置为不同值的时候
# wait = WebDriverWait(driver,5,1)#设置5秒每1秒去寻找一次
# back_button = wait.until(lambda x:x.find_elements_by_xpath("//*[@content-desc='收起']"))
# back_button.click()

#输入和清空输入框内容
# driver.find_element_by_id("com.android.settings:id/search").click()
#
# driver.find_element_by_class_name("android.widget.EditText").send_keys("ssss")
# time.sleep(2)
# driver.find_element_by_class_name("android.widget.EditText").clear()

#获取元素文本内容、位置、大小、属性
# element.text
# element.location
# element.size
# titles = driver.find_element_by_id("com.android.settings:id/search")
# for title in titles:
#     print(title.get_attribute("text"))
#     print(title.get_attribute("resourceId"))


###屏幕滑动###
# #swipe
# start_x=100 #起点x轴坐标
# start_y=2000 #起点y轴坐标
# end_x=100 #终点x轴坐标
# end_y =1000#终点y轴坐标
# duration = 300 #滑动这个操作一共持续的时间长度，单位：毫秒
# driver.swipe(start_x,start_y,end_x,end_y)#参数是坐标点，持续时间短，则惯性大，反之，惯性小

#scroll（从一个元素滑动到另外一个元素，直到页面自动停止）
# origin_el=driver.find_element_by_xpath("//*[@text='蓝牙']")#滑动开始的元素
# destination_el=driver.find_element_by_xpath("//*[@text='打印']")#滑动结束的元素
# driver.scroll(origin_el,destination_el)#从蓝牙滑动到打印，不能设置持续时间，惯性很大

#drag_and_drop滑动事件（从一个元素滑动到另外一个元素，第二个元素替代第一个元素原来的位置）
# driver.drag_and_drop(origin_el,destination_el)#不能设置持续时间，没有惯性

driver.quit()  # 关闭驱动对象，同时关闭所有关联的app
