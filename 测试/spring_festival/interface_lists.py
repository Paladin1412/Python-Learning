import pymongo
import json

with open("../../config.json", "r") as fp:
    ret = json.load(fp)
    host = ret['host']
    port = ret['mongo']['port']
    user = ret['mongo']['user']
    pwd = ret['mongo']['pwd']
cli = pymongo.MongoClient(host=host, port=port, username=user, password=pwd, authSource='admin')
collection = cli['bilibili']['spring_festival_four_player']

with open("interface_list.json","r",encoding='utf-8') as f:
    in_ret = json.load(f)
    data=[]
    data.extend(in_ret)
    collection.insert_many(data)
cli.close()
