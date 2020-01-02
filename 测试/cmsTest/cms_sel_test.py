from selenium import webdriver
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By

userList=[{"user":"test1","pwd":"7P40T31Skc"}]
driver = webdriver.Chrome()
driver.get("http://uat-tv-cp.bilibili.co/#/login")
locatorLogin = (By.CLASS_NAME,"input-control")
print(locatorLogin)
try:
    WebDriverWait(driver,20,0.5).until(EC.presence_of_all_elements_located(locatorLogin))
    print("Success!")
except:
    print("Failed!")
for i in userList:
        driver.find_element_by_name("username").send_keys(i['user'])
        driver.find_element_by_name("password").send_keys(i['pwd'])
        while True:
            code = input("Please enter code:")
            driver.find_element_by_name("verify").send_keys(code)
            driver.find_element_by_class_name("login-btn").click()
            loginSuccessFlag = input("Success?")
            if loginSuccessFlag == "t":
                break
            else:
                driver.find_element_by_class_name()

