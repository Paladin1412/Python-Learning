import pymysql
import re

pattern = re.compile(r'd/.*?"')
db = pymysql.connect(host='192.168.11.31',port=30001,user='michael',password='cccbbb',database='ly_web')
with db.cursor(cursor=pymysql.cursors.DictCursor) as cursor:
    cursor.execute("select id,paper_link from papers")
    ret = cursor.fetchall()
    change_list=[]
    for i in ret:
        if i['paper_link'] =="无":
            continue
        try:
            dicts={}
            dicts['id']=i['id']

            link = pattern.search(i['paper_link']).group()
            link = link.replace('d/','').replace('"','')
            link = "http://cdn.lingyunsh.cn/downloads/papers/"+link
            dicts['paper_link'] =link
            change_list.append(dicts)

        except Exception as e:
            print(e)
    for link in change_list:
        sql="UPDATE papers SET paper_link='{}' WHERE id = '{}'".format(link['paper_link'], link['id'])
        print(sql)
        cursor.execute(sql)
        db.commit()
db.close()

