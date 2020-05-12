import re
lists = []
cpu_lists = []
mem_lists = []
mem_max = 0
def cal_avg(lists):
    sums = 0
    for i in lists:
        sums += i
    return sums / len(lists)
with open('./log1.txt','r') as fp:
    for line in fp.readlines():
        if line:
            s = re.search(r'\{(.*?)\}',line)
            lists.append(eval(s.group()))
[cpu_lists.append(float(i['app_cpu_usage'])) for i in lists]
[mem_lists.append(float(i['app_memory_usage'])) for i in lists]
avg_cpu = cal_avg(cpu_lists)
avg_mem = cal_avg(mem_lists)
print("应用cpu平均使用率为:%.2f" % avg_cpu + "%")
print("应用内存平均使用率为:%.2f" % avg_mem + "%")
print("内存最大使用率为:%.2f" % max(mem_lists) + "%")