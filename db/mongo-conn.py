import pymongo
import json
with open("../config.json","r") as fp:
    ret = json.load(fp)
    host = ret['host']
    port = ret['mongo']['port']
    user = ret['mongo']['user']
    pwd = ret['mongo']['pwd']
cli = pymongo.MongoClient(host=host,port=port,username=user,password=pwd,authSource='admin')
db = cli['mydb']
collection = db['images']
#JSON导入
with open("images.json", encoding="utf-8") as jf:
    str = jf.read()
    data = []
    data.extend(json.loads(str))
    collection.insert_many(data)
cli.close() #写完关闭连接

#JSON导出
output = []
for i in collection.find():
    output.append(i)