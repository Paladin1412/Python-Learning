import pymysql
import re

pattern = re.compile(r'/.*?"')
db = pymysql.connect(host='192.168.11.31',port=30001,user='michael',password='cccbbb',database='ly_web')
with db.cursor(cursor=pymysql.cursors.DictCursor) as cursor:
    cursor.execute("select paper_link from papers")
    ret = cursor.fetchall()
    for i in ret:
        if i['paper_link'] =="无":
            continue
        print(i['paper_link'])
        link = pattern.search(i['paper_link']).group()
        print(link)


db.close()

