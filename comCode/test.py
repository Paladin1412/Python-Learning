import requests
url = "http://data.snm0516.aisee.tv/log/mobile?android"
cookie={
    "mng-go":"5a864bd2bf9b9ac8df91e28d83b5d29d61ffa63ee60a4168a4f8950b26c681d1",
"_AJSESSIONID":"aa5dfa1d9b814026f62c78c50116c8d9",
"username":"",
"_gitlab_session":"caa4ea968aacf1158cb43b89122ea9c5",
"PHPSESSID":"5jrsjshjqs2dngjftn4ku1hsf4"
}
url2="http://uat-tv-cp.bilibili.co/api/v4/tvcms/searchInter/lists"
headers={
    "User-Agent":"Dalvik/2.1.0 (Linux; U; Android 6.0.1; MiBOX4 Build/MOB31S)",
    "Host":"data.snm0516.aisee.tv"
}
data="00022515765707063522|XY55BDA572475241E58527D511EBF71A32720|1576568611|1|master|73|Xiaomi|GChJeUlwEncRdxQhXSEZeEx1E3YQcTEJbhp8SDpGd0BxQnZZa1NjVGRWZ1Nm|MiBOX4|6.0.1|20191217161825||1.2.3|1||tv_login_click|click|XY55BDA572475241E58527D511EBF71A32720|1|	"
ret = requests.post(url=url,data=data,headers=headers)
ret2 = requests.get(url=url2,cookies=cookie)
print(ret2.text)