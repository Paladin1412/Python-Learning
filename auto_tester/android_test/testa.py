from selenium import webdriver
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
driver = webdriver.Firefox(
    executable_path=
    "/auto_tester/geckodriver")
driver.get("https://www.baidu.com")
now_window = driver.current_window_handle
driver.find_element_by_id("kw").send_keys("上海理工大学研究生")
driver.find_element_by_id('su').click()

locatorLogin = (By.ID,"1")
try:
    WebDriverWait(driver,20,0.5).until(EC.presence_of_all_elements_located(locatorLogin))
    print("Success!")
except:
    print("Failed!")
ret1 = driver.find_element_by_xpath('//div[@id="1"]/h3/a')
#右键
# ActionChains(driver).context_click(ret1).perform()
ret1.click()

handles = driver.window_handles
for tab in handles:
    if tab != now_window:
        driver.switch_to.window(tab)
driver.close()
