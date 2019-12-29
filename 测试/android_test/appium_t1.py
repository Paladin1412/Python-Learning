from appium import webdriver
import time


desired_caps={}
desired_caps['platformName']='Android'     #系统名称
desired_caps['platformVersion']='6.0'    #系统的版本号
desired_caps['deviceName']='127.0.0.1:5554'     #设备名称，这里是虚拟机，这个没有严格的规定
desired_caps['appPackage']='com.android.settings'    #APP包名
desired_caps['appActivity']='.Settings'       #APP入口的activity
#连接appium server，告诉appium，代码要操作哪个设备上的哪个APP
driver=webdriver.Remote('http://127.0.0.1:4723/wd/hub',desired_caps)

time.sleep(3)
#跳转到浏览器
driver.start_activity('com.android.browser','.BrowserActivity')
driver.quit()
#获取包名
cp = driver.current_package
#获取界面名
ca = driver.current_activity
print(cp,ca)