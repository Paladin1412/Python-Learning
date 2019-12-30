from appium import webdriver
import time

desired_caps = {}
desired_caps['platformName'] = 'Android'  # 系统名称
desired_caps['platformVersion'] = '6.0'  # 系统的版本号
desired_caps['deviceName'] = '127.0.0.1:5554'  # 设备名称，这里是虚拟机，这个没有严格的规定
desired_caps['appPackage'] = 'com.android.settings'  # APP包名
desired_caps['appActivity'] = '.Settings'  # APP入口的activity
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

#将应用置于后台5秒钟再返回前台
# driver.background_app(5)
# time.sleep(5)
# driver.close_app()#只关闭操作的app，不关闭驱动对象


driver.quit()  # 关闭驱动对象，同时关闭所有关联的app
